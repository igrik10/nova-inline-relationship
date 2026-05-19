<?php

namespace KirschbaumDevelopment\NovaInlineRelationship\Rules;

use Illuminate\Support\MessageBag;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class RelationshipRule implements Rule
{
    public $rules = [];

    /**
     * @var array
     */
    protected $messages;

    /**
     * @var array
     */
    protected $attributes;

    /**
     * @var MessageBag
     */
    protected $response;

    /**
     * Create a new rule instance.
     *
     * @param array $rules
     * @param null|mixed $messages
     * @param null|mixed $attributes
     *
     */
    public function __construct(array $rules, $messages = null, $attributes = null)
    {
        $this->rules = $rules;
        $this->messages = $messages;
        $this->attributes = $attributes;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $data = is_array($value) ? $value : json_decode($value, true);

        // Inline-relationship payloads are wrapped as
        // [{ values: {...}, modelId: x }, ...] (see NovaInlineRelationship::getResourceResponse()).
        // Rule paths are emitted as `{attribute}.*.{childAttribute}`, so we unwrap the
        // `values` envelope before validating to keep both sides aligned.
        $hasValuesEnvelope = is_array($data) && ! empty($data) && $this->isValuesEnvelope($data);

        $normalized = $hasValuesEnvelope
            ? array_map(static fn ($item) => $item['values'] ?? [], $data)
            : $data;

        $input = [$attribute => $normalized];

        $validator = Validator::make($input, $this->rules, $this->messages, $this->attributes);

        $errors = $validator->errors()->getMessages();

        $this->response = [];
        foreach ($errors as $key => $message) {
            $rewrittenKey = $hasValuesEnvelope
                ? $this->rewriteErrorKey($attribute, $key)
                : $key;

            $this->response[$rewrittenKey] = is_array($message) ? ($message[0] ?? '') : $message;
        }

        return $validator->passes();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->response;
    }

    /**
     * Detect whether each item in the payload is wrapped as `{ values: [...], modelId: ... }`.
     *
     * @param array $data
     *
     * @return bool
     */
    protected function isValuesEnvelope(array $data): bool
    {
        foreach ($data as $item) {
            if (! is_array($item) || ! array_key_exists('values', $item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rewrite an error key from `{attribute}.{index}.{child}` back to
     * `{attribute}.{index}.values.{child}` so consumers can resolve it
     * against the original (envelope-shaped) payload.
     *
     * @param string $attribute
     * @param string $key
     *
     * @return string
     */
    protected function rewriteErrorKey(string $attribute, string $key): string
    {
        $prefix = $attribute . '.';

        if (! str_starts_with($key, $prefix)) {
            return $key;
        }

        $remainder = substr($key, strlen($prefix));
        $parts = explode('.', $remainder, 2);

        if (count($parts) !== 2) {
            return $key;
        }

        return $prefix . $parts[0] . '.values.' . $parts[1];
    }
}
