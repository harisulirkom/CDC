<?php

namespace App\Support;

class EmailTemplateDefaults
{
    public static function alumniBlast(): array
    {
        return [
            'subject' => 'Tracer Study Alumni - CDC UIN Kediri',
            'body' => implode("\n", [
                'Yth. {nama},',
                '',
                'Ini adalah email dari CDC UIN Kediri sebagai langkah untuk melakukan survey sebaran lulusan.',
                '',
                'Biodata alumni kami:',
                'Nama: {nama}',
                'Program studi: {prodi}',
                'Tahun lulus: {tahun_lulus}',
                '',
                'Mohon mengisi tracer study melalui tautan berikut:',
                '{link}',
                '',
                'Terima kasih.',
            ]),
        ];
    }
}
