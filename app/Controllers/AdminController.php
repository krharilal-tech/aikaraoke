<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Models\Credit;
use App\Models\Package;
use App\Models\User;

final class AdminController extends Controller
{
    public function users(Request $request): void
    {
        $this->requireAdmin();

        $this->view('admin/users', [
            'pageTitle' => 'Admin — Users',
            'users' => User::allWithBalance(),
            'flash' => flash_message('admin_status'),
        ]);
    }

    public function adjustCredits(Request $request): void
    {
        $this->requireAdmin();
        $this->requireCsrf($request);

        $userId = Sanitizer::int($request->param('id'));
        $delta = (int) $request->input('delta', 0);
        $reason = trim(Sanitizer::string($request->input('reason', ''), 190));

        $user = User::find($userId);

        if ($user === null) {
            Response::notFound('User not found.');
        }

        if ($delta === 0) {
            Session::flash('admin_status', ['type' => 'error', 'message' => 'Enter a non-zero credit amount.']);
            $this->redirect(base_url('admin/users'));
        }

        Credit::grant($userId, $delta, $reason !== '' ? $reason : Credit::REASON_ADMIN_ADJUSTMENT);

        $verb = $delta > 0 ? 'Granted' : 'Deducted';
        Session::flash('admin_status', [
            'type' => 'success',
            'message' => "{$verb} " . abs($delta) . ' credit(s) for ' . $user['email'] . '.',
        ]);

        $this->redirect(base_url('admin/users'));
    }

    public function packages(Request $request): void
    {
        $this->requireAdmin();

        $this->view('admin/packages', [
            'pageTitle' => 'Admin — Packages',
            'packages' => Package::allOrdered(),
            'flash' => flash_message('admin_status'),
        ]);
    }

    public function createPackage(Request $request): void
    {
        $this->requireAdmin();
        $this->requireCsrf($request);

        $data = $this->validatePackageInput($request);

        if ($data === null) {
            $this->redirect(base_url('admin/packages'));
        }

        Package::create($data);

        Session::flash('admin_status', ['type' => 'success', 'message' => 'Package created.']);
        $this->redirect(base_url('admin/packages'));
    }

    public function updatePackage(Request $request): void
    {
        $this->requireAdmin();
        $this->requireCsrf($request);

        $id = Sanitizer::int($request->param('id'));

        if (Package::find($id) === null) {
            Response::notFound('Package not found.');
        }

        $data = $this->validatePackageInput($request);

        if ($data === null) {
            $this->redirect(base_url('admin/packages'));
        }

        Package::update($id, $data);

        Session::flash('admin_status', ['type' => 'success', 'message' => 'Package updated.']);
        $this->redirect(base_url('admin/packages'));
    }

    public function togglePackage(Request $request): void
    {
        $this->requireAdmin();
        $this->requireCsrf($request);

        $id = Sanitizer::int($request->param('id'));
        $package = Package::find($id);

        if ($package === null) {
            Response::notFound('Package not found.');
        }

        Package::setActive($id, !(bool) $package['is_active']);

        $this->redirect(base_url('admin/packages'));
    }

    /**
     * @return array<string, mixed>|null null means validation failed — a
     * flash error was already queued and the caller should redirect back.
     */
    private function validatePackageInput(Request $request): ?array
    {
        $name = trim(Sanitizer::string($request->input('name', ''), 100));
        $credits = Sanitizer::int($request->input('credits', 0));
        $price = (float) $request->input('price_inr', 0);
        $sortOrder = Sanitizer::int($request->input('sort_order', 0));

        if ($name === '' || $credits <= 0 || $price <= 0) {
            Session::flash('admin_status', [
                'type' => 'error',
                'message' => 'Please provide a name, a positive credit count, and a positive price.',
            ]);

            return null;
        }

        return [
            'name' => $name,
            'credits' => $credits,
            'price_inr' => round($price, 2),
            'sort_order' => $sortOrder,
        ];
    }

    private function requireAdmin(): void
    {
        // 404, not 403 — an admin section's existence isn't information a
        // logged-in non-admin user needs confirmed.
        if (!Auth::isAdmin()) {
            Response::notFound();
        }
    }
}
