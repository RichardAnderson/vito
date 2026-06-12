<?php

namespace App\Pages\SiteSettings\Components;

use App\Pages\Contracts\SchemaNode;
use App\Pages\DataEndpoint;
use App\Pages\PageAction;

/**
 * A reusable slice of a settings page: the rows it contributes to a card and any
 * behaviour (actions/data) it carries with no row of its own. Rows are spread into a
 * Card by the host page; headless behaviour is merged into the page's headless().
 * Both are harvested for routing, so a section's actions inherit the page's
 * defaultAuthorize like any other.
 */
abstract class Section
{
    /**
     * @return array<int, SchemaNode>
     */
    public function rows(): array
    {
        return [];
    }

    /**
     * @return array<int, PageAction|DataEndpoint>
     */
    public function headless(): array
    {
        return [];
    }
}
