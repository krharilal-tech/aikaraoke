"""
Writes the per-job JSON status file that PHP polls (GET /api/jobs/{id}/status).

This is the only channel Python has back to the app — the worker never opens
a database connection. Writes are atomic (write-to-temp + os.replace) so a
concurrent read from PHP never sees a half-written file.
"""

from __future__ import annotations

import json
import os
import time
from pathlib import Path
from typing import Any


class StatusWriter:
    MAX_LOG_LINES = 300

    def __init__(self, job_dir: str | Path) -> None:
        self.status_path = Path(job_dir) / "status.json"
        existing = self._read()
        self._logs: list[str] = list(existing.get("logs", []))

    def _read(self) -> dict[str, Any]:
        if not self.status_path.exists():
            return {}

        try:
            return json.loads(self.status_path.read_text(encoding="utf-8"))
        except (json.JSONDecodeError, OSError):
            return {}

    def update(self, **fields: Any) -> dict[str, Any]:
        data = self._read()
        data.update(fields)
        data["updated_at"] = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())

        tmp_path = self.status_path.with_suffix(".tmp")
        tmp_path.write_text(json.dumps(data, indent=2), encoding="utf-8")
        self._replace_with_retry(tmp_path, self.status_path)

        return data

    @staticmethod
    def _replace_with_retry(src: Path, dst: Path, attempts: int = 20, base_delay: float = 0.1) -> None:
        """os.replace() on Windows can transiently raise "[WinError 5]
        Access is denied" when another process has the destination
        momentarily open — Windows Defender's real-time scanner briefly
        opening a just-written file is the usual cause, and status.json is
        rewritten very frequently (every log line), so it's a repeat
        target. This is a well-documented Windows filesystem race, not a
        logic bug — a retry-with-backoff is the standard fix.

        A real job hit this hard enough to exhaust an earlier, shorter
        retry budget (10 attempts / ~2.75s) — worse, the *failure-reporting*
        status.update() call in worker.py's except block hit the exact same
        lock and also failed, so the process died with an unhandled
        exception and status.json was left frozen forever with no error
        ever recorded (the job just looked permanently "stuck", not
        "failed"). Two changes address that: a bigger retry budget here
        (worst case ~21s, since a lock that outlasts 2.75s clearly can
        outlast it by more), and — the actual safety net — a non-atomic
        direct-write fallback below if every rename attempt still fails.
        Losing atomicity on that rare path (a concurrent PHP read could in
        theory see a partial write) is a far better failure mode than the
        update being lost entirely and the job hanging with no error."""

        last_error: OSError | None = None

        for attempt in range(attempts):
            try:
                os.replace(src, dst)
                return
            except OSError as exc:
                last_error = exc
                time.sleep(base_delay * (attempt + 1))

        try:
            dst.write_bytes(src.read_bytes())
            src.unlink(missing_ok=True)
            return
        except OSError:
            pass

        assert last_error is not None
        raise last_error

    def log(self, message: str) -> None:
        line = f"[{time.strftime('%H:%M:%S')}] {message}"
        self._logs.append(line)
        self._logs = self._logs[-self.MAX_LOG_LINES:]
        self.update(logs=self._logs)

    def snapshot(self) -> dict[str, Any]:
        return self._read()

    def set_metadata(self, **metadata: Any) -> None:
        current = self._read().get("metadata") or {}
        current.update({k: v for k, v in metadata.items() if v is not None})
        self.update(metadata=current)
