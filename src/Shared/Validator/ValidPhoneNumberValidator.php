<?php

declare(strict_types=1);

namespace App\Shared\Validator;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidPhoneNumberValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPhoneNumber) {
            throw new UnexpectedTypeException($constraint, ValidPhoneNumber::class);
        }

        if (null === $value || '' === $value) {
            // L'obligation de renseigner le champ relève de `Assert\NotBlank`,
            // posée séparément là où le téléphone est requis.
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($value, $constraint->defaultRegion);
        } catch (NumberParseException) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        if (!$util->isValidNumber($number)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
