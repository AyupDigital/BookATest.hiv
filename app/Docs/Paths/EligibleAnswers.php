<?php

namespace App\Docs\Paths;

use App\Docs\Requests;
use App\Docs\Resources\EligibleAnswerResource;
use App\Docs\Responses;
use App\Docs\Tags;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class EligibleAnswers
{
    /**
     * EligibleAnswers constructor.
     */
    public function __construct()
    {
        // Prevent instantiation.
    }

    /**
     * @return \GoldSpecDigital\ObjectOrientedOAS\Objects\Operation
     */
    public static function index(): Operation
    {
        $description = <<<EOT
**Permission:** `Clinic Admin`
- Can view all eligible answers at a clinic they are a `Clinic Admin` for

***

This endpoint will only return a list of all the eligibility answers for the current questions.

It's important to realise that answers for previous questions cannot be accessed through the API, even though they
remain in the database.
EOT;

        $responses = [
            Responses::http200(
                MediaType::json(EligibleAnswerResource::all())
            ),
        ];
        $parameters = [
            Parameter::path('clinic', Schema::string()->format(Schema::UUID))
                ->description('The clinic ID')
                ->required(),
        ];

        return Operation::get(...$responses)
            ->parameters(...$parameters)
            ->summary('List all the eligible answers set by the clinic')
            ->description($description)
            ->operationId('clinics.eligible-answers.show')
            ->tags(Tags::eligibleAnswers()->name);
    }

    /**
     * @return \GoldSpecDigital\ObjectOrientedOAS\Objects\Operation
     */
    public static function update(): Operation
    {
        $description = <<<EOT
**Permission:** `Clinic Admin`
- Can update the set of eligible answers for a clinic they are a `Clinic Admin` for

***

This endpoint can only be used if the client has already specified their eligible answers for the current set of
questions.
EOT;

        $responses = [
            Responses::http200(
                MediaType::json(EligibleAnswerResource::all())
            ),
        ];
        $parameters = [
            Parameter::path('clinic', Schema::string()->format(Schema::UUID))
                ->description('The clinic ID')
                ->required(),
        ];
        $requestBody = Requests::json(Schema::object()
            ->required('question_id', 'answer')
            ->properties(
                Schema::string('question_id')->format(Schema::UUID),
                Schema::object('answer')
                    ->required('type', 'interval')
                    ->properties(
                        Schema::string('type'),
                        Schema::integer('interval')
                    )
            )
        );

        return Operation::put(...$responses)
            ->parameters(...$parameters)
            ->requestBody($requestBody)
            ->summary('Update the eligibility answers')
            ->description($description)
            ->operationId('clinics.eligible-answers.update')
            ->tags(Tags::eligibleAnswers()->name);
    }
}