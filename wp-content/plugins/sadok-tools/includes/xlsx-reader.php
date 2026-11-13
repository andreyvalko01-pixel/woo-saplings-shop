<?php
/**
 * Мінімальний читач .xlsx без сторонніх бібліотек: xlsx — це zip з XML усередині.
 * Читає перший аркуш, повертає масив рядків (перший рядок — заголовки).
 */

defined('ABSPATH') || exit;

function sadok_read_xlsx(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Не вдалося відкрити файл. Це точно .xlsx?');
    }

    // текстові значення зберігаються окремо, у клітинках лише їхні індекси
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $doc = simplexml_load_string($xml);
        foreach ($doc->si as $si) {
            // текст може бути розбитий на кілька фрагментів з різним форматуванням
            $text = isset($si->t) ? (string) $si->t : '';
            foreach ($si->r ?? [] as $run) {
                $text .= (string) $run->t;
            }
            $shared[] = $text;
        }
    }

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet === false) {
        throw new RuntimeException('У файлі немає аркуша з даними');
    }

    $rows = [];
    foreach (simplexml_load_string($sheet)->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            // "C12" -> номер колонки 2 (A=0)
            preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
            $col = 0;
            foreach (str_split($m[1]) as $letter) {
                $col = $col * 26 + (ord($letter) - 64);
            }
            $value = (string) $c->v;
            if ((string) $c['t'] === 's') {
                $value = $shared[(int) $value] ?? '';
            } elseif ((string) $c['t'] === 'inlineStr') {
                $value = (string) $c->is->t;
            }
            $cells[$col - 1] = trim($value);
        }
        if ($cells) {
            $max = max(array_keys($cells));
            $rows[] = array_replace(array_fill(0, $max + 1, ''), $cells);
        }
    }
    return $rows;
}
