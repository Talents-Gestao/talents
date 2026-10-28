<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PortugueseLabels;
use Tests\TestCase;

class PortugueseLabelsTest extends TestCase
{
    public function test_formats_common_segment_slugs_with_accents(): void
    {
        $this->assertSame('Serviços', PortugueseLabels::segment('servicos'));
        $this->assertSame('Serviços', PortugueseLabels::segment('SERVICOS'));
        $this->assertSame('Comércio', PortugueseLabels::segment('comercio'));
        $this->assertSame('Indústria', PortugueseLabels::segment('industria'));
        $this->assertSame('Saúde', PortugueseLabels::segment('saude'));
        $this->assertSame('Educação', PortugueseLabels::segment('educacao'));
    }

    public function test_keeps_unknown_or_empty_values(): void
    {
        $this->assertNull(PortugueseLabels::segment(null));
        $this->assertSame('', PortugueseLabels::segment('  '));
        $this->assertSame('Consultoria estratégica', PortugueseLabels::segment('Consultoria estratégica'));
    }
}
