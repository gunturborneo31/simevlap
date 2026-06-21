<?php

namespace Database\Seeders;

use App\Models\Opd;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LegacyUsersSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('referensi/database/users.sql');
        if (!File::exists($path)) {
            return;
        }

        $sql = File::get($path);
        $tuples = $this->extractTuples($sql);
        if (count($tuples) === 0) {
            return;
        }

        $opdByKode = Opd::query()
            ->select(['id', 'kode'])
            ->get()
            ->mapWithKeys(fn (Opd $opd) => [trim((string) $opd->kode) => (int) $opd->id]);

        foreach ($tuples as $tuple) {
            $fields = str_getcsv($tuple, ',', "'", '\\');
            if (count($fields) < 10) {
                continue;
            }

            $name = trim((string) $fields[1]);
            $legacyLogin = trim((string) $fields[2]);
            $password = trim((string) $fields[4]);
            $idSkpd = trim((string) $fields[5]);
            $legacyRole = strtolower(trim((string) $fields[6]));
            $isSuperadmin = $legacyRole === 'superadmin' || strtolower($legacyLogin) === 'superadmin';

            if ($isSuperadmin || $legacyLogin === '' || $password === '') {
                continue;
            }

            [$name, $legacyLogin] = $this->normalizeLegacyIdentity($name, $legacyLogin, $legacyRole);

            // Simpan login lama apa adanya di kolom email agar user tetap pakai akun lama.
            $email = $legacyLogin;
            $opdId = $opdByKode[$idSkpd] ?? null;
            $role = $this->mapLegacyRole($legacyRole);

            $user = User::query()
                ->where('email', $email)
                ->orWhere(function ($query) use ($legacyRole) {
                    if ($legacyRole === 'pimpinan') {
                        $query->where('email', 'tamu');
                    }
                })
                ->first();

            if ($user) {
                $user->update([
                    'email' => $email,
                    'name' => $name !== '' ? $name : $email,
                    'password' => $password,
                    'opd_id' => $opdId,
                    'is_active' => true,
                ]);
            } else {
                $user = User::query()->create([
                    'email' => $email,
                    'name' => $name !== '' ? $name : $email,
                    'password' => $password,
                    'opd_id' => $opdId,
                    'is_active' => true,
                ]);
            }

            $user->syncRoles([$role]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeLegacyIdentity(string $name, string $legacyLogin, string $legacyRole): array
    {
        if ($legacyRole === 'pimpinan' && strtolower($legacyLogin) === 'tamu') {
            return ['Pimpinan', 'pimpinan'];
        }

        return [$name, $legacyLogin];
    }

    private function mapLegacyRole(string $legacyRole): string
    {
        return match ($legacyRole) {
            'pimpinan' => 'pimpinan',
            'skpd' => 'opd',
            default => 'opd',
        };
    }

    /**
     * @return array<int, string>
     */
    private function extractTuples(string $sql): array
    {
        if (!preg_match('/INSERT INTO `users`\s*\([^;]*?\)\s*VALUES\s*(.*?);/si', $sql, $matches)) {
            return [];
        }

        $valuesBlock = $matches[1];
        $tuples = [];
        $buffer = '';
        $depth = 0;
        $inString = false;
        $prev = '';

        $len = strlen($valuesBlock);
        for ($i = 0; $i < $len; $i++) {
            $ch = $valuesBlock[$i];

            if ($ch === "'" && $prev !== '\\') {
                $inString = !$inString;
            }

            if (!$inString && $ch === '(') {
                $depth++;
                if ($depth === 1) {
                    $buffer = '';
                    $prev = $ch;
                    continue;
                }
            }

            if (!$inString && $ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = $buffer;
                    $buffer = '';
                    $prev = $ch;
                    continue;
                }
            }

            if ($depth >= 1) {
                $buffer .= $ch;
            }

            $prev = $ch;
        }

        return $tuples;
    }
}