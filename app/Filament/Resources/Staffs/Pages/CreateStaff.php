<?php

namespace App\Filament\Resources\Staffs\Pages;

use App\Filament\Resources\Staffs\StaffResource;
use App\Models\Staff;
use App\Models\User;
use App\Support\Phone;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $mode = strtolower(trim((string) ($data['user_mode'] ?? 'existing')));

        if ($mode === 'new') {
            $user = $this->createNewUserFromStaffForm($data);
            $data['user_id'] = (int) $user->id;
        }

        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId <= 0) {
            throw ValidationException::withMessages([
                'user_id' => 'Chagua mtumiaji au tengeneza user mpya kwanza.',
            ]);
        }

        if (Staff::query()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'Mtumiaji huyu tayari yupo kwenye staff.',
            ]);
        }

        unset(
            $data['user_mode'],
            $data['new_user_name'],
            $data['new_user_phone'],
            $data['new_user_email'],
            $data['new_user_password'],
            $data['new_user_password_confirmation'],
        );

        $data['notes'] = isset($data['notes']) ? trim((string) $data['notes']) : null;
        if ($data['notes'] === '') {
            $data['notes'] = null;
        }

        return $data;
    }

    private function createNewUserFromStaffForm(array $data): User
    {
        $name = trim((string) ($data['new_user_name'] ?? ''));
        $phoneRaw = trim((string) ($data['new_user_phone'] ?? ''));
        $email = strtolower(trim((string) ($data['new_user_email'] ?? '')));
        $password = (string) ($data['new_user_password'] ?? '');
        $passwordConfirmation = (string) ($data['new_user_password_confirmation'] ?? '');

        $errors = [];

        $phone = null;
        if ($phoneRaw !== '') {
            $phone = Phone::normalizeTzMsisdn($phoneRaw);
            if ($phone === null) {
                $errors['new_user_phone'][] = 'Weka namba sahihi ya simu (07XXXXXXXX au 2557XXXXXXXX).';
            }
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['new_user_email'][] = 'Weka email sahihi.';
        }

        if ($phone === null && $email === '') {
            $errors['new_user_phone'][] = 'Weka angalau simu au email ya login.';
        }

        if (strlen($password) < 6) {
            $errors['new_user_password'][] = 'Password lazima iwe na angalau herufi 6.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['new_user_password_confirmation'][] = 'Password hazifanani.';
        }

        if ($phone !== null && User::query()->where('phone', $phone)->exists()) {
            $errors['new_user_phone'][] = 'Namba hii tayari inatumika.';
        }

        if ($email !== '' && User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $errors['new_user_email'][] = 'Email hii tayari inatumika.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        if ($name === '') {
            $name = $phone ?: $email;
        }
        if ($name === '') {
            $name = 'Staff User';
        }

        return User::query()->create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email !== '' ? $email : null,
            'password' => Hash::make($password),
            'role' => 'client',
            'otp_verified_at' => $phone ? now() : null,
            'email_verified_at' => $email !== '' ? now() : null,
        ]);
    }
}
