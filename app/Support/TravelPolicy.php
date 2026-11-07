<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\PolicyDocument;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Support\Facades\File;

class TravelPolicy
{
    public static function template(): string
    {
        $path = base_path('docs/politicas-de-viaje-plantilla.md');
        if (File::exists($path)) {
            return File::get($path);
        }

        return 'Política de viaje de NavegaYA. Equipaje de mano 10 kg, bodega 20 kg, aéreo 10 kg y carga desde 50 kg.';
    }

    public static function templateHtml(): string
    {
        return PolicyHtml::clean(self::markdownToHtml(self::template()));
    }

    public static function markdownToHtml(string $markdown): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown) ?: [];
        $html = [];
        $buffer = [];
        $mode = null;

        $flush = function () use (&$buffer, &$mode, &$html): void {
            if ($buffer === []) {
                $mode = null;

                return;
            }

            if ($mode === 'p') {
                $html[] = '<p>'.implode('<br>', array_map(self::inline(...), $buffer)).'</p>';
            } elseif ($mode === 'ul' || $mode === 'ol') {
                $items = '';
                foreach ($buffer as $item) {
                    $items .= '<li>'.self::inline($item).'</li>';
                }
                $html[] = "<{$mode}>{$items}</{$mode}>";
            } elseif ($mode === 'quote') {
                $html[] = '<blockquote><p>'.implode('<br>', array_map(self::inline(...), $buffer)).'</p></blockquote>';
            } elseif ($mode === 'table') {
                $html[] = self::tableHtml($buffer);
            }

            $buffer = [];
            $mode = null;
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $flush();

                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/', $trim, $heading)) {
                $flush();
                $tag = 'h1';
                $html[] = "<{$tag}>".self::inline($heading[2])."</{$tag}>";

                continue;
            }

            if ($trim === '---') {
                $flush();
                $html[] = '<hr>';

                continue;
            }

            if (str_starts_with($trim, '>')) {
                if ($mode !== 'quote') {
                    $flush();
                    $mode = 'quote';
                }
                $buffer[] = ltrim(substr($trim, 1));

                continue;
            }

            if (str_starts_with($trim, '|')) {
                if ($mode !== 'table') {
                    $flush();
                    $mode = 'table';
                }
                $buffer[] = $trim;

                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $trim, $item)) {
                if ($mode !== 'ol') {
                    $flush();
                    $mode = 'ol';
                }
                $buffer[] = $item[1];

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trim, $item)) {
                if ($mode !== 'ul') {
                    $flush();
                    $mode = 'ul';
                }
                $buffer[] = $item[1];

                continue;
            }

            if ($mode !== 'p') {
                $flush();
                $mode = 'p';
            }
            $buffer[] = $trim;
        }

        $flush();

        return implode("\n", array_filter($html, fn (string $part): bool => $part !== ''));
    }

    private static function inline(string $text): string
    {
        $text = e($text);
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text) ?? $text;

        return preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $text) ?? $text;
    }

    /**
     * @param  list<string>  $rows
     */
    private static function tableHtml(array $rows): string
    {
        $parsed = [];
        foreach ($rows as $row) {
            $cells = array_values(array_map(trim(...), explode('|', trim($row, " \t|"))));
            $rule = $cells !== [] && array_all($cells, fn (string $cell): bool => (bool) preg_match('/^:?-+:?$/', $cell));
            if ($rule || $cells === []) {
                continue;
            }
            $parsed[] = $cells;
        }

        if ($parsed === []) {
            return '';
        }

        $head = array_shift($parsed);
        $html = '';
        foreach ($parsed as $row) {
            $lines = [];
            foreach ($head as $index => $label) {
                $lines[] = '<strong>'.self::inline($label).':</strong> '.self::inline($row[$index] ?? '');
            }
            $html .= '<p>'.implode('<br>', $lines).'</p>';
        }

        return $html;
    }

    public static function ensureBase(): PolicyDocument
    {
        $document = PolicyDocument::query()->firstOrCreate(
            ['kind' => 'travel_base', 'organization_id' => null],
            ['title' => 'Política de viaje base'],
        );
        if (! $document->versions()->exists()) {
            $document->versions()->create([
                'version' => 1,
                'summary' => 'Plantilla base de NavegaYA.',
                'body_html' => self::templateHtml(),
                'effective_on' => now()->toDateString(),
                'published_at' => now(),
            ]);
        }

        return $document;
    }

    public static function ensureCompany(Organization $organization): PolicyDocument
    {
        $base = self::ensureBase()->versions()->whereNotNull('published_at')->orderByDesc('version')->first();
        $document = PolicyDocument::query()->firstOrCreate(
            ['kind' => 'company_travel', 'organization_id' => $organization->id],
            ['title' => 'Política de viaje de '.($organization->commercial_name ?: $organization->legal_name)],
        );
        if (! $document->versions()->exists()) {
            $document->versions()->create([
                'version' => 1,
                'summary' => 'Copia de la plantilla base.',
                'body_html' => $base?->body_html ?: self::templateHtml(),
                'effective_on' => now()->toDateString(),
                'published_at' => now(),
            ]);
        }

        return $document->load('versions');
    }

    public static function publish(PolicyDocument $document, string $html, ?string $summary, ?User $editor, ?string $reason): PolicyVersion
    {
        $next = ((int) $document->versions()->max('version')) + 1;

        return $document->versions()->create([
            'version' => $next,
            'summary' => $summary,
            'body_html' => PolicyHtml::clean($html),
            'effective_on' => now()->toDateString(),
            'published_at' => now(),
            'edited_by' => $editor?->id,
            'reason' => $reason,
        ]);
    }

    public static function currentPlatformTerms(): ?PolicyVersion
    {
        return PolicyVersion::query()
            ->whereNotNull('published_at')
            ->whereHas('document', fn ($query) => $query->where('kind', 'platform_terms')->whereNull('organization_id'))
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return list<string>
     */
    public static function publicPoints(Organization $organization): array
    {
        $baggage = BaggageAllowance::for($organization);

        return [
            'Equipaje fluvial: mano '.$baggage['hand'].' kg y bodega '.$baggage['hold'].' kg.',
            'Equipaje aéreo: '.$baggage['air'].' kg.',
            'Si el equipaje supera '.$baggage['cargo'].' kg se registra como envío de carga.',
            'Si la empresa cancela o reprograma, el pasajero elige reembolso total o cambio sin costo.',
            'Si el pasajero desiste, aplican los plazos de la empresa, informados antes de pagar.',
        ];
    }
}
