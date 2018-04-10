<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 06/04/2018
 * Time: 1:39 AM
 */

class DooInputValidator
{
    public $rules;

    function __construct($rules = null)
    {
        if (!empty($rules)) {
            $this->rules = $this->getFieldRules($rules);
        }
    }

    protected function getFieldRules($fieldRules = null)
    {
        $rules = [];

        foreach ($fieldRules as $fname => $p) {
            if ($fname == '_method') {
                continue;
            }
            if (isset($p[1])) {
                $rules[$fname] = $p[1];
            }
        }
        return $rules;
    }

    public function validateInput($input, $fieldRules = null)
    {
        if (!empty($fieldRules)) {
            $rules = $this->getFieldRules($fieldRules);
        } else {
            $rules = $this->rules;
        }

        if ($input == null) {
            //check if only one and is optional skip send error
            $fieldKeys = array_keys($rules);

            $oneAndOptional = false;
            $actFieldList = $rules;
            $fieldCount = 0;
            $optionalCount = 0;

            foreach ($actFieldList as $fieldName => $field) {
                if ($fieldName{0} != '_') {
                    $fieldCount++;
                }

                //check if ['optional'] is set for the field if there's only one field
                //$field[1] = validation list, 0 is the type.
                if (isset($field[1])) {
                    foreach ($field[1] as $vl) {
                        if (is_array($vl) && $vl[0] == 'optional') {
                            $optionalCount++;
                        }
                    }
                }
            }

            if ($fieldCount == $optionalCount) {
                $oneAndOptional = true;
            }

            if (!$oneAndOptional) {
                return new DooInputValidatorResult(DooInputValidatorResult::INVALID_NO_INPUT);
            }
        }

        if ($rules == null) {
            return new DooInputValidatorResult(DooInputValidatorResult::INVALID_NO_RULES);
        }

        $v = new DooValidator();
        $v->checkMode = DooValidator::CHECK_ALL;
        $err = $v->validate($input, $rules);

        if ($err) {
            return new DooInputValidatorResult(DooInputValidatorResult::INVALID_RULE_ERRORS, $err);
        }

        return new DooInputValidatorResult(DooInputValidatorResult::VALID, null, $input);
    }
}

class DooInputValidatorResult
{

    const VALID = 1;
    const INVALID_NO_INPUT = 0;
    const INVALID_NO_RULES = -1;
    const INVALID_RULE_ERRORS = -2;

    public $inputValues;
    public $errors;
    public $resultType;

    function __construct($resultType, $errors, $inputValues = null)
    {
        $this->resultType = $resultType;
        $this->errors = $errors;
        $this->inputValues = $inputValues;
    }
}