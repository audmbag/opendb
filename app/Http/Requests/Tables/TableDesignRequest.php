<?php

namespace App\Http\Requests\Tables;

use App\Services\TableDesigner\TableDesignValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A table as the designer describes it: its name, its columns, its primary key, its indexes and its foreign keys.
 * The elements already in the database keep their name in `original`, the new ones have none.
 *
 * @phpstan-import-type TableDesign from \App\Services\TableDesigner\TableDesignReader
 */
class TableDesignRequest extends FormRequest
{
    /**
     * A name written without quotes by every engine. An existing name the user keeps is accepted as it is.
     */
    public const string IDENTIFIER = TableDesignValidator::IDENTIFIER;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $table = $this->route('table');

        return $this->designValidator()->rules($this->all(), is_string($table) ? $table : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->designValidator()->messages();
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return $this->designValidator()->after($this->all());
    }

    /**
     * @return TableDesign
     */
    public function design(): array
    {
        return $this->designValidator()->normalize($this->validated());
    }

    protected function designValidator(): TableDesignValidator
    {
        return app(TableDesignValidator::class);
    }
}
