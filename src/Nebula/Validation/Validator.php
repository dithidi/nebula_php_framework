<?php

namespace Nebula\Validation;

class Validator
{
    /**
     * Performs a validation on an array of data based on rules.
     *
     * @param array $data The data to validate.
     * @param array $rules The validation rules.
     * @param bool $apiResponse Indicates whether
     * @return mixed
     */
    public function validate($data, $rules, $apiResponse = false)
    {
        $errors = [];

        // Loop through validation rules and perform validation.
        foreach ($rules as $key => $ruleSet) {
            // Parse the rule
            $ruleArray = explode('|', $ruleSet);

            foreach ($ruleArray as $rule) {
                // Break apart rule if necessary
                $ruleParts = explode(':', $rule);

                if (isset($ruleParts[1])) {
                    $validationResult = $this->{$ruleParts[0]}($key, $data ?? null, $ruleParts[1]);
                } else {
                    $validationResult = $this->{$ruleParts[0]}($key, $data ?? null);
                }

                if ($validationResult !== true) {
                    $errors[$key][] = $validationResult;
                }
            }
        }

        if (!empty($errors)) {
            if (!empty($apiResponse)) {
                return response()->json(['errors' => $errors], 400);
            }

            return back()->withInputs()->with('errors', $errors);
        }

        return true;
    }

    /**
     * Executes a required validation.
     *
     * @param array $key The key of the data.
     * @param array $data The data entry for validaion.
     * @return mixed
     */
    private function required($key, $data)
    {
        if (!isset($data[$key])) {
            $key = str_replace('_', ' ', $key);
            return "The $key field is required.";
        }

        return true;
    }

    /**
     * Executes an email validation.
     *
     * @param array $key The key of the data.
     * @param array $data The data entry for validaion.
     * @return mixed
     */
    private function email($key, $data)
    {
        if (array_key_exists($key, $data) && !filter_var($data[$key], FILTER_VALIDATE_EMAIL)) {
            $key = str_replace('_', ' ', $key);
            return "The $key field must be a valid email address.";
        }

        return true;
    }

    /**
     * Determines if a field is equal to another.
     *
     * @param string $key The key of the data.
     * @param array $data The data entry for validaion.
     * @param string $comparison The field to compare.
     * @return mixed
     */
    private function equalTo($key, $data, $comparison)
    {
        if (array_key_exists($key, $data) && $data[$key] != $data[$comparison]) {
            $key = str_replace('_', ' ', $key);
            $comparisonKey = str_replace('_', ' ', $comparison);

            return "The $key field must match the $comparison field.";
        }

        return true;
    }

    /**
     * Determines if a field is in a list.
     *
     * @param string $key The key of the data.
     * @param array $data The data entry for validaion.
     * @param string $list The list of acceptable values.
     * @return mixed
     */
    private function in($key, $data, $list)
    {
        $inValues = explode(',', $list);

        if (array_key_exists($key, $data) && !in_array($data[$key], $inValues)) {
            $key = str_replace('_', ' ', $key);

            return "The $key field must match one of the following values: " . str_replace(',', ', ', $list) . ".";
        }

        return true;
    }

    /**
     * Determines if a field is above a maximum value.
     *
     * @param string $key The key of the data.
     * @param array $data The data entry for validaion.
     * @param string $value The maximum allowed value.
     * @return mixed
     */
    private function max($key, $data, $value)
    {
        if (array_key_exists($key, $data)) {
            if (is_string($data[$key]) && strlen($data[$key]) > $value) {
                $key = str_replace('_', ' ', $key);
                return "The $key field must not be greater than $value characters.";
            } elseif (is_numeric($data[$key]) && $data[$key] > $value) {
                $key = str_replace('_', ' ', $key);
                return "The $key field must not be greater than $value.";
            }
        }

        return true;
    }

    /**
     * Determines if a field is above a minimum value.
     *
     * @param string $key The key of the data.
     * @param array $data The data entry for validaion.
     * @param string $value The minimum allowed value.
     * @return mixed
     */
    private function min($key, $data, $value)
    {
        if (array_key_exists($key, $data)) {
            if (is_string($data[$key]) && strlen($data[$key]) < $value) {
                $key = str_replace('_', ' ', $key);
                return "The $key field must be greater than $value characters.";
            } elseif (is_numeric($data[$key]) && $data[$key] < $value) {
                $key = str_replace('_', ' ', $key);
                return "The $key field must be greater than $value.";
            }
        }

        return true;
    }
}
