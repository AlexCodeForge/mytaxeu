<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Upload;
use App\Models\User;

class UploadPolicy
{
    /**
     * Determine whether the user can view any uploads.
     */
    public function viewAny(User $user): bool
    {
        // Users can view their own uploads list
        return true;
    }

    /**
     * Determine whether the user can view the upload.
     */
    public function view(User $user, Upload $upload): bool
    {
        // Users can only view their own uploads, admins can view all
        return $user->isAdmin() || $upload->user_id === $user->id;
    }

    /**
     * Determine whether the user can create uploads.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create uploads
        return true;
    }

    /**
     * Determine whether the user can update the upload.
     */
    public function update(User $user, Upload $upload): bool
    {
        // Only admins can update uploads (for status changes, etc.)
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the upload.
     */
    public function delete(User $user, Upload $upload): bool
    {
        // Users can delete their own uploads, admins can delete any
        return $user->isAdmin() || $upload->user_id === $user->id;
    }

    /**
     * Determine whether the user can download the upload file.
     */
    public function download(User $user, Upload $upload): bool
    {
        // Users can download their own uploads, admins can download any
        return $user->isAdmin() || $upload->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the upload.
     */
    public function restore(User $user, Upload $upload): bool
    {
        // Only admins can restore uploads
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the upload.
     */
    public function forceDelete(User $user, Upload $upload): bool
    {
        // Only admins can force delete uploads
        return $user->isAdmin();
    }
}
