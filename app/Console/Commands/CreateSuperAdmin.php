<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:super-admin
                            {email? : Email akun super admin}
                            {--name= : Nama yang ditampilkan}
                            {--password= : Kata sandi minimal 12 karakter; dikosongkan agar dibuat acak}';

    protected $description = 'Membuat akun super admin baru atau mengatur ulang kata sandinya';

    public function handle(): int
    {
        $email = Str::lower((string) ($this->argument('email') ?: ($this->input->isInteractive() ? $this->ask('Email super admin') : '')));
        $name = (string) ($this->option('name') ?: 'Super Admin');
        $password = (string) ($this->option('password') ?: Str::password(20));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email', 'max:255'],
                'name' => ['required', 'string', 'max:100'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->option('name') ?: $user->name ?: $name;
        $user->role = 'super_admin';
        $user->password = $password;
        $user->email_verified_at ??= now();
        $user->save();

        $this->components->info($user->wasRecentlyCreated ? 'Akun super admin dibuat.' : 'Akun super admin diperbarui.');
        $this->table(['Email', 'Nama', 'Kata sandi'], [[$user->email, $user->name, $password]]);
        $this->components->warn('Simpan kata sandi sekarang: nilai ini tidak dapat dilihat lagi. Jalankan perintah yang sama dengan --password untuk menggantinya.');

        return self::SUCCESS;
    }
}
