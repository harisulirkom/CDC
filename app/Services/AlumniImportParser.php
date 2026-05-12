<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use ZipArchive;

class AlumniImportParser
{
    public const MODE_SMART = 'smart';
    public const MODE_STRICT = 'strict';

    /** @var array<string, string> */
    protected array $headerMap = [
        'nama' => 'nama',
        'name' => 'nama',
        'nama_lengkap' => 'nama',
        'full_name' => 'nama',
        'nim' => 'nim',
        'no_induk_mahasiswa' => 'nim',
        'nik' => 'nik',
        'no_ktp' => 'nik',
        'ktp' => 'nik',
        'prodi' => 'prodi',
        'program_studi' => 'prodi',
        'programstudi' => 'prodi',
        'study_program' => 'prodi',
        'fakultas' => 'fakultas',
        'faculty' => 'fakultas',
        'tahun_lulus' => 'tahun_lulus',
        'tahunlulusan' => 'tahun_lulus',
        'graduation_year' => 'tahun_lulus',
        'tahun_masuk' => 'tahun_masuk',
        'entry_year' => 'tahun_masuk',
        'email' => 'email',
        'email_address' => 'email',
        'mail' => 'email',
        'no_hp' => 'no_hp',
        'nohp' => 'no_hp',
        'nomor_hp' => 'no_hp',
        'hp' => 'no_hp',
        'phone' => 'no_hp',
        'alamat' => 'alamat',
        'address' => 'alamat',
        'tanggal_lahir' => 'tanggal_lahir',
        'tgl_lahir' => 'tanggal_lahir',
        'tanggallahir' => 'tanggal_lahir',
        'birth_date' => 'tanggal_lahir',
        'dob' => 'tanggal_lahir',
        'foto' => 'foto',
        'photo' => 'foto',
        'status_pekerjaan' => 'status_pekerjaan',
        'job_status' => 'status_pekerjaan',
        'sent' => 'sent',
    ];

    /** @return array<string, mixed> */
    public function preflight(string $fullPath, string $mode = self::MODE_SMART): array
    {
        return $this->parseFile($fullPath, $mode, false);
    }

    /** @return array<string, mixed> */
    public function parseFile(string $fullPath, string $mode = self::MODE_SMART, bool $includeRecords = true): array
    {
        $mode = in_array($mode, [self::MODE_SMART, self::MODE_STRICT], true) ? $mode : self::MODE_SMART;
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $format = in_array($extension, ['xlsx', 'xls'], true) ? 'xlsx' : 'csv';

        $delimiter = ',';
        $encoding = 'UTF-8';
        $rows = [];

        if ($format === 'xlsx') {
            $rows = $this->readXlsxRows($fullPath);
        } else {
            [$rows, $delimiter, $encoding] = $this->readCsvRows($fullPath);
        }

        if (count($rows) === 0) {
            return [
                'mode' => $mode,
                'format' => $format,
                'delimiter' => $delimiter,
                'encoding' => $encoding,
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'can_import' => false,
                'missing_required_headers' => ['nama', 'nim'],
                'mapped_headers' => [],
                'errors' => [['row' => 1, 'message' => 'File kosong atau tidak dapat dibaca.']],
                'records' => [],
            ];
        }

        $headerRaw = $rows[0];
        $mappedHeaders = $this->mapHeaders($headerRaw);
        $missingRequiredHeaders = $this->missingRequiredHeaders($mappedHeaders);
        $errors = [];
        $records = [];
        $validRows = 0;
        $invalidRows = 0;
        $autoFixedEmailCount = 0;
        $emailSeen = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rowNumber = $i + 1;
            $mapped = $this->mapRowToRecord($mappedHeaders, $row);
            $normalized = $this->normalizeRecord($mapped, $mode, $rowNumber, $emailSeen);

            if (!empty($normalized['errors'])) {
                $invalidRows++;
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => implode('; ', $normalized['errors']),
                ];
            } else {
                $validRows++;
                if (!empty($normalized['meta']['email_auto_fixed'])) {
                    $autoFixedEmailCount++;
                }
            }

            if ($includeRecords) {
                $records[] = [
                    'row_number' => $rowNumber,
                    'data' => $normalized['data'],
                    'errors' => $normalized['errors'],
                    'meta' => $normalized['meta'] ?? [],
                ];
            }
        }

        if (!empty($missingRequiredHeaders)) {
            $errors[] = [
                'row' => 1,
                'message' => 'Header wajib tidak ditemukan: ' . implode(', ', $missingRequiredHeaders),
            ];
        }

        return [
            'mode' => $mode,
            'format' => $format,
            'delimiter' => $delimiter,
            'encoding' => $encoding,
            'total_rows' => $validRows + $invalidRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'can_import' => empty($missingRequiredHeaders) && $validRows > 0,
            'missing_required_headers' => $missingRequiredHeaders,
            'mapped_headers' => array_values(array_filter($mappedHeaders)),
            'auto_fixed_email_count' => $autoFixedEmailCount,
            'errors' => array_slice($errors, 0, 200),
            'records' => $records,
        ];
    }

    /** @return array<int, array<int, string>> */
    protected function readXlsxRows(string $fullPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($fullPath) !== true) {
            return [];
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath = $this->resolveFirstWorksheetPath($zip);
            if (!$sheetPath) {
                return [];
            }

            $sheetXml = $zip->getFromName($sheetPath);
            if (!$sheetXml) {
                return [];
            }

            $xml = @simplexml_load_string($sheetXml);
            if (!$xml) {
                return [];
            }

            $rows = [];
            foreach ($xml->sheetData->row as $rowNode) {
                $cells = [];
                $maxIndex = -1;

                foreach ($rowNode->c as $cell) {
                    $ref = (string) ($cell['r'] ?? '');
                    $colIndex = $this->columnIndexFromCellReference($ref);
                    if ($colIndex < 0) {
                        continue;
                    }

                    $value = $this->extractXlsxCellValue($cell, $sharedStrings);
                    $cells[$colIndex] = $value;
                    if ($colIndex > $maxIndex) {
                        $maxIndex = $colIndex;
                    }
                }

                if ($maxIndex < 0) {
                    continue;
                }

                $row = [];
                for ($i = 0; $i <= $maxIndex; $i++) {
                    $row[] = isset($cells[$i]) ? (string) $cells[$i] : '';
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    protected function readSharedStrings(ZipArchive $zip): array
    {
        $xmlBody = $zip->getFromName('xl/sharedStrings.xml');
        if (!$xmlBody) {
            return [];
        }

        $xml = @simplexml_load_string($xmlBody);
        if (!$xml) {
            return [];
        }

        $result = [];
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $result[] = (string) $si->t;
                continue;
            }

            $chunks = [];
            foreach ($si->r as $run) {
                $chunks[] = (string) ($run->t ?? '');
            }
            $result[] = implode('', $chunks);
        }

        return $result;
    }

    protected function resolveFirstWorksheetPath(ZipArchive $zip): ?string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!$workbookXml || !$relsXml) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $workbook = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);
        if (!$workbook || !$rels) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = $workbook->xpath('//m:sheets/m:sheet');
        if (!$sheets || !isset($sheets[0])) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $attrs = $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rid = (string) ($attrs['id'] ?? '');
        if ($rid === '') {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relationship = $rels->xpath("//r:Relationship[@Id='{$rid}']");
        if (!$relationship || !isset($relationship[0])) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $target = (string) ($relationship[0]['Target'] ?? '');
        if ($target === '') {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $target = str_replace('\\', '/', $target);
        $target = ltrim($target, '/');
        $full = Str::startsWith($target, 'xl/') ? $target : 'xl/' . $target;
        return $zip->locateName($full) !== false ? $full : null;
    }

    protected function columnIndexFromCellReference(string $reference): int
    {
        if ($reference === '' || !preg_match('/^([A-Z]+)\d+$/i', $reference, $m)) {
            return -1;
        }

        $letters = strtoupper($m[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    protected function extractXlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');
        if ($type === 's') {
            $index = (int) ((string) ($cell->v ?? 0));
            return (string) ($sharedStrings[$index] ?? '');
        }

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        if ($type === 'b') {
            return ((string) ($cell->v ?? '0')) === '1' ? '1' : '0';
        }

        return (string) ($cell->v ?? '');
    }

    /** @return array{0: array<int, array<int, string>>, 1: string, 2: string} */
    protected function readCsvRows(string $fullPath): array
    {
        $bytes = @file_get_contents($fullPath);
        if ($bytes === false) {
            return [[], ',', 'UTF-8'];
        }

        $encoding = $this->detectEncoding($bytes);
        $content = $this->convertToUtf8($bytes, $encoding);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $delimiter = $this->detectDelimiter($content);
        $handle = fopen('php://temp', 'r+');
        if (!$handle) {
            return [[], $delimiter, $encoding];
        }

        try {
            fwrite($handle, $content);
            rewind($handle);
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($row === [null] || $row === false) {
                    continue;
                }
                $rows[] = array_map(static fn ($v) => is_string($v) ? trim($v) : (string) $v, $row);
            }
            return [$rows, $delimiter, $encoding];
        } finally {
            fclose($handle);
        }
    }

    protected function detectEncoding(string $bytes): string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }
        if (str_starts_with($bytes, "\xFF\xFE")) {
            return 'UTF-16LE';
        }
        if (str_starts_with($bytes, "\xFE\xFF")) {
            return 'UTF-16BE';
        }

        $detected = mb_detect_encoding($bytes, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'Windows-1252', 'ISO-8859-1'], true);
        return $detected ?: 'UTF-8';
    }

    protected function convertToUtf8(string $bytes, string $encoding): string
    {
        if (strtoupper($encoding) === 'UTF-8') {
            return $bytes;
        }

        $converted = @mb_convert_encoding($bytes, 'UTF-8', $encoding);
        return is_string($converted) ? $converted : $bytes;
    }

    protected function detectDelimiter(string $content): string
    {
        $line = '';
        foreach (explode("\n", $content) as $candidate) {
            if (trim($candidate) !== '') {
                $line = $candidate;
                break;
            }
        }

        if ($line === '') {
            return ',';
        }

        $scores = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
            '|' => substr_count($line, '|'),
        ];
        arsort($scores);
        $best = array_key_first($scores);
        return $scores[$best] > 0 ? $best : ',';
    }

    /** @param array<int, string> $headers */
    protected function mapHeaders(array $headers): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            $mapped[$index] = $this->headerMap[$normalized] ?? null;
        }
        return $mapped;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = trim(preg_replace('/^\xEF\xBB\xBF/', '', $header));
        return (string) Str::of($header)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
    }

    /** @param array<int, ?string> $mappedHeaders */
    protected function missingRequiredHeaders(array $mappedHeaders): array
    {
        $required = ['nama', 'nim'];
        $present = array_values(array_filter($mappedHeaders));
        return array_values(array_diff($required, $present));
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    /** @param array<int, ?string> $mappedHeaders */
    protected function mapRowToRecord(array $mappedHeaders, array $row): array
    {
        $data = [];
        foreach ($row as $index => $value) {
            $column = $mappedHeaders[$index] ?? null;
            if (!$column) {
                continue;
            }
            $data[$column] = trim((string) $value);
        }
        return $data;
    }

    /** @param array<string, int> $emailSeen
     *  @return array{data: array<string,mixed>, errors: array<int,string>, meta: array<string,mixed>}
     */
    protected function normalizeRecord(array $row, string $mode, int $rowNumber, array &$emailSeen): array
    {
        $errors = [];
        $emailAutoFixed = false;
        $nim = $this->cleanText($row['nim'] ?? null);
        $nama = $this->cleanText($row['nama'] ?? null);
        $prodi = $this->cleanText($row['prodi'] ?? null);
        $fakultas = $this->cleanText($row['fakultas'] ?? null);
        $nik = $this->cleanText($row['nik'] ?? null);
        $noHp = $this->cleanText($row['no_hp'] ?? null);
        $alamat = $this->cleanText($row['alamat'] ?? null);
        $foto = $this->cleanText($row['foto'] ?? null);
        $statusPekerjaan = $this->cleanText($row['status_pekerjaan'] ?? null);
        $tahunMasuk = $this->normalizeYear($row['tahun_masuk'] ?? null);
        $tahunLulus = $this->normalizeYear($row['tahun_lulus'] ?? null);
        $tanggalLahir = $this->normalizeDate($row['tanggal_lahir'] ?? null);
        $email = $this->normalizeEmail($row['email'] ?? null);

        if (!$nim) {
            $errors[] = 'NIM wajib diisi';
        }
        if (!$nama) {
            $errors[] = 'Nama wajib diisi';
        }

        if (!$email || !$this->isValidEmail($email)) {
            if ($mode === self::MODE_STRICT) {
                $errors[] = 'Email tidak valid';
            } else {
                $email = $this->buildFallbackEmail($nim ?: ('row' . $rowNumber));
                $emailAutoFixed = true;
            }
        }

        if ($email) {
            if (isset($emailSeen[$email])) {
                if ($mode === self::MODE_STRICT) {
                    $errors[] = 'Email duplikat dalam file';
                } else {
                    $email = $this->buildFallbackEmail(($nim ?: 'row' . $rowNumber) . '-' . $rowNumber);
                    $emailAutoFixed = true;
                }
            }
            $emailSeen[$email] = 1;
        }

        if ($mode === self::MODE_STRICT && ($row['tanggal_lahir'] ?? null) && !$tanggalLahir) {
            $errors[] = 'Format tanggal lahir tidak valid';
        }

        $data = [
            'nama' => $nama,
            'nim' => $nim,
            'nik' => $nik,
            'prodi' => $prodi,
            'fakultas' => $fakultas,
            'tahun_masuk' => $tahunMasuk,
            'tahun_lulus' => $tahunLulus,
            'email' => $email,
            'no_hp' => $noHp,
            'alamat' => $alamat,
            'tanggal_lahir' => $tanggalLahir,
            'foto' => $foto,
            'status_pekerjaan' => $statusPekerjaan,
            'sent' => false,
        ];

        return [
            'data' => $data,
            'errors' => $errors,
            'meta' => [
                'email_auto_fixed' => $emailAutoFixed,
            ],
        ];
    }

    protected function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    protected function normalizeYear(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', (string) $value);
        if (strlen($digits) < 4) {
            return null;
        }
        $year = (int) substr($digits, 0, 4);
        return ($year >= 1900 && $year <= 2100) ? $year : null;
    }

    protected function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $serial = (float) $raw;
            if ($serial > 0 && $serial < 100000) {
                $timestamp = (int) round(($serial - 25569) * 86400);
                return gmdate('Y-m-d', $timestamp);
            }
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $raw : null;
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        try {
            $dt = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
            return $dt->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $email = strtolower(trim($value));
        return $email === '' ? null : $email;
    }

    protected function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function buildFallbackEmail(string $seed): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]/', '', $seed));
        if ($base === '') {
            $base = 'alumni';
        }
        return $base . '@import.local';
    }
}
