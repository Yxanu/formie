<?php

declare(strict_types=1);

use verbb\formie\elements\Submission;

beforeEach(function (): void {
    $this->optionLimitLanguage = Craft::$app->language;
    Craft::$app->language = 'en-US';
});

afterEach(function (): void {
    Craft::$app->language = $this->optionLimitLanguage;
});

dataset('empty_option_limit_fields', [
    'checkboxes' => ['checkboxesField', []],
    'multi-select dropdown' => ['dropdownField', ['multi' => true]],
]);

it('validates empty option selections as a count of zero', function (
    string $method, array $config, mixed $input, array $limits, ?string $expectedError
): void {
    $form = formie()->form()->$method('choices', $config + $limits + [
        'label' => 'Selection',
        'options' => [['label' => 'One', 'value' => 'one']],
    ])->create();
    $submission = new Submission();
    $submission->setForm($form);
    $submission->setFieldValueFromRequest('choices', $input);

    // All supported empty request shapes normalize to null, not a countable value.
    expect($submission->getFieldValue('choices'))->toBeNull();

    $result = runSubmissionCommand(submissionCommand(['form' => $form, 'submission' => $submission]));
    $stored = Submission::find()->formId($form->id)->status(null)->isSpam(null)->isIncomplete(null)->all();

    if ($expectedError !== null) {
        expect($result->success)->toBeFalse()
            ->and($result->submission->getErrors('choices'))->toBe([$expectedError])
            ->and($result->submission->id)->toBeNull()
            ->and($stored)->toBeEmpty();
    } else {
        expect($result->success)->toBeTrue()
            ->and($result->submission->getErrors())->toBeEmpty()
            ->and($stored)->toHaveCount(1)
            ->and($stored[0]->getFieldValue('choices'))->toBeNull();
    }
})->with('empty_option_limit_fields')->with([
    'null' => [null],
    'empty array' => [[]],
    'empty string' => [''],
    'blank option' => [['']],
])->with([
    'minimum one' => [['limitOptions' => true, 'min' => 1], 'Selection should contain at least 1 option.'],
    'minimum two' => [['limitOptions' => true, 'min' => 2], 'Selection should contain at least 2 options.'],
    'optional maximum only' => [['limitOptions' => true, 'max' => 1], null],
    'disabled limits' => [['limitOptions' => false, 'min' => 1], null],
    'custom minimum message' => [[
        'limitOptions' => true,
        'min' => 1,
        'validationMessages' => ['minOptions' => 'Select at least {min}.'],
    ], 'Select at least 1.'],
]);

it('preserves option count limits for nonempty selections', function (
    string $method, array $config, array $limits, array $input, ?string $expectedError
): void {
    $form = formie()->form()->$method('choices', $config + $limits + [
        'label' => 'Selection',
        'limitOptions' => true,
        'options' => [
            ['label' => 'Zero', 'value' => '0'],
            ['label' => 'One', 'value' => 'one'],
            ['label' => 'Two', 'value' => 'two'],
        ],
    ])->create();
    $submission = new Submission();
    $submission->setForm($form);
    $submission->setFieldValueFromRequest('choices', $input);
    $result = runSubmissionCommand(submissionCommand(['form' => $form, 'submission' => $submission]));
    $stored = Submission::find()->formId($form->id)->status(null)->isSpam(null)->isIncomplete(null)->all();

    if ($expectedError !== null) {
        expect($result->success)->toBeFalse()
            ->and($result->submission->getErrors('choices'))->toBe([$expectedError])
            ->and($stored)->toBeEmpty();
    } else {
        expect($result->success)->toBeTrue()
            ->and($result->submission->getErrors())->toBeEmpty()
            ->and($stored)->toHaveCount(1)
            ->and($stored[0]->getFieldValueAsData('choices'))->toBe($input);
    }
})->with('empty_option_limit_fields')->with([
    'below minimum' => [['min' => 2], ['one'], 'Selection should contain at least 2 options.'],
    'at minimum' => [['min' => 1], ['one'], null],
    'above maximum' => [['max' => 1], ['one', 'two'], 'Selection should contain at most 1 option.'],
    'at maximum' => [['max' => 2], ['one', 'two'], null],
    'zero-valued option' => [['min' => 1], ['0'], null],
]);
