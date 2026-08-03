"""
HTTP-based counterpart to StatusWriter, used when the pipeline runs on
RunPod instead of locally alongside PHP. Implements the exact same
interface (update/log/snapshot/set_metadata) that every stage script
already calls — this is the only piece of the pipeline that needed to
change to support running remotely; every stages/*.py and services/*.py
file besides this one is unmodified and runs identically in both places.

Where StatusWriter persists state by writing status.json to a local disk
PHP can read, this POSTs the same JSON shape to a callback endpoint on the
PHP app (see App\\Controllers\\WorkerCallbackController), which saves it
locally on PHP's side and syncs it into the database — from PHP's
perspective nothing downstream (the status-poll API, the progress page)
needs to know or care which StatusWriter produced it.
"""

from __future__ import annotations

import time
from typing import Any

import requests


class RemoteStatusWriter:
    MAX_LOG_LINES = 300
    REQUEST_TIMEOUT_SECONDS = 15

    def __init__(self, job_id: int, callback_base_url: str, worker_secret: str) -> None:
        self.job_id = job_id
        self._status_url = f"{callback_base_url.rstrip('/')}/api/worker/jobs/{job_id}/status"
        self._headers = {"X-Worker-Secret": worker_secret, "Content-Type": "application/json"}
        self._data: dict[str, Any] = {"job_id": job_id, "logs": []}
        self._logs: list[str] = []

    def update(self, **fields: Any) -> dict[str, Any]:
        self._data.update(fields)
        self._data["updated_at"] = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())

        self._post()

        return self._data

    def _post(self) -> None:
        # A single dropped status push isn't fatal — the next update() call
        # (there's one after every log line) will carry the same
        # information forward, self-healing on the next successful post.
        # Crashing the whole job over a transient network hiccup talking
        # to Hostinger would be a much worse failure mode than one missed
        # progress tick.
        try:
            requests.post(
                self._status_url,
                json=self._data,
                headers=self._headers,
                timeout=self.REQUEST_TIMEOUT_SECONDS,
            )
        except requests.RequestException as exc:
            print(f"RemoteStatusWriter: status push failed (continuing): {exc}", flush=True)

    def log(self, message: str) -> None:
        line = f"[{time.strftime('%H:%M:%S')}] {message}"
        print(line, flush=True)
        self._logs.append(line)
        self._logs = self._logs[-self.MAX_LOG_LINES:]
        self.update(logs=self._logs)

    def snapshot(self) -> dict[str, Any]:
        return dict(self._data)

    def set_metadata(self, **metadata: Any) -> None:
        current = self._data.get("metadata") or {}
        current.update({k: v for k, v in metadata.items() if v is not None})
        self.update(metadata=current)
