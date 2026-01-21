<?php

namespace App\Tests\Validator;

use App\Validator\BanWord;
use App\Validator\BanWordValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class BanWordValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new BanWordValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new BanWord());
        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new BanWord());
        $this->assertNoViolation();
    }

    public function testValidTextNoViolation(): void
    {
        $this->validator->validate('texte ok', new BanWord());
        $this->assertNoViolation();
    }

    public function testDefaultBanWordsAreDetectedCaseInsensitive(): void
    {
        $constraint = new BanWord();
        $this->validator->validate('Achetez du SPAM maintenant', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ banWord }}', 'spam')
            ->assertRaised();
    }

    public function testCustomBanWordsAndMessage(): void
    {
        $constraint = new BanWord(message: 'no {{ banWord }}', banWords: ['bad']);
        $this->validator->validate('this is bad', $constraint);

        $this->buildViolation('no {{ banWord }}')
            ->setParameter('{{ banWord }}', 'bad')
            ->assertRaised();
    }
}




