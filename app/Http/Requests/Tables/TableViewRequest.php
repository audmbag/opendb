<?php

namespace App\Http\Requests\Tables;

use App\Services\TableBrowser\Data\TableView;
use App\Services\TableBrowser\FilterOperator;
use App\Services\TableBrowser\TableExportWriter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The filters, sorts and page of the rows of a table, as the grid sends them.
 */
class TableViewRequest extends FormRequest
{
    public const array PAGE_SIZES = [50, 100, 250, 500];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filters' => ['array', 'max:20'],
            'filters.*.column' => ['required', 'string'],
            'filters.*.operator' => ['required', Rule::enum(FilterOperator::class)],
            'filters.*.value' => ['required_unless:filters.*.operator,is_null,is_not_null'],
            'match' => [Rule::in(['all', 'any'])],
            'sorts' => ['array', 'max:5'],
            'sorts.*.column' => ['required', 'string'],
            'sorts.*.direction' => [Rule::in(['asc', 'desc'])],
            'page' => ['integer', 'min:1'],
            'perPage' => ['integer', Rule::in(self::PAGE_SIZES)],
            'format' => [Rule::in(TableExportWriter::FORMATS)],
        ];
    }

    public function view(): TableView
    {
        return TableView::fromArray($this->validated());
    }
}
