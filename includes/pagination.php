<?php
/** Pagination terpusat — aturan pengguna: urutan menurun (terbaru dulu), 10 baris per halaman. */
define('PER_PAGE', 10);

/** Halaman aktif dari query string (?hal=N), minimal 1. */
function page_current(): int
{
    $page = (int) ($_GET['hal'] ?? 1);
    return $page < 1 ? 1 : $page;
}

function page_offset(int $page, int $perPage = PER_PAGE): int
{
    return max(0, ($page - 1) * $perPage);
}

function page_total(int $totalRows, int $perPage = PER_PAGE): int
{
    return max(1, (int) ceil($totalRows / $perPage));
}

/** URL halaman dengan parameter `hal`. */
function page_url(string $baseUrl, int $page): string
{
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    return $baseUrl . $separator . 'hal=' . $page;
}

/** Render bar pagination (Sebelumnya, nomor, Berikutnya) — aman HTML. */
function page_render(string $baseUrl, int $page, int $totalPages): void
{
    if ($totalPages <= 1) {
        return;
    }

    $numbers = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2) {
            $numbers[] = $i;
        }
    }

    echo '<nav class="pager" aria-label="Navigasi halaman">';
    if ($page > 1) {
        echo '<a class="pager-link" href="' . e(page_url($baseUrl, $page - 1)) . '">‹ Sebelumnya</a>';
    } else {
        echo '<span class="pager-link is-disabled">‹ Sebelumnya</span>';
    }

    $previous = 0;
    foreach ($numbers as $number) {
        if ($previous !== 0 && $number - $previous > 1) {
            echo '<span class="pager-gap">…</span>';
        }
        if ($number === $page) {
            echo '<span class="pager-link is-active">' . $number . '</span>';
        } else {
            echo '<a class="pager-link" href="' . e(page_url($baseUrl, $number)) . '">' . $number . '</a>';
        }
        $previous = $number;
    }

    if ($page < $totalPages) {
        echo '<a class="pager-link" href="' . e(page_url($baseUrl, $page + 1)) . '">Berikutnya ›</a>';
    } else {
        echo '<span class="pager-link is-disabled">Berikutnya ›</span>';
    }
    echo '</nav>';
}
