<?php

declare(strict_types=1);

namespace App\Core;

/** Pelanggaran aturan bisnis; pesannya aman ditampilkan ke pengguna sebagai toast (docs/14). */
final class DomainRuleException extends \RuntimeException
{
}
