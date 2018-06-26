<?php

namespace RstGroup\Recruitment\ConferenceSystem\Test;

use RstGroup\Recruitment\ConferenceSystem\Conference\Conference;
use RstGroup\Recruitment\ConferenceSystem\Conference\ConferenceRepository;
use RstGroup\Recruitment\ConferenceSystem\Discount\DiscountService;

class DiscountServiceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider discountsDataProvider
     */
    public function testCalculateDiscountProperly($conferenceId, $attendantsCount, $price, $discountsTypes, $groupDiscounts, $code, $codeUsed, $codeDiscount, $expectedDiscount)
    {
        /** @var DiscountService $discountService */
        $discountService = $this->getMockBuilder(DiscountService::class)->setMethods(['getConferencesRepository'])->getMock();
        
        //Usuwając ConferenceRepository z klasy DiscountService (lub pochodnych) będziemy mogli uzyskać krótszy i czytelniejszy
        //kod testwy, bo będzie potrzebne zamokowanie tylko właściwego obiektu Conference - nie ma tutaj potrzeby na więcej.
        $conferenceRepository = $this->getMock(ConferenceRepository::class);

        $discountService->expects($this->once())
            ->method('getConferencesRepository')
            ->willReturn($conferenceRepository);

        $conference = $this->getMock(Conference::class);

        $conferenceRepository->expects($this->once())
            ->method('getConference')
            ->with($conferenceId)
            ->willReturn($conference);

        if ($groupDiscounts !== null) {
            $conference->expects($this->once())
                ->method('getGroupDiscount')
                ->willReturn($groupDiscounts);
        }

        if ($code !== null && !in_array('group', $discountsTypes)) {
            $conference->expects($this->at(0))
                ->method('isCodeNotUsed')
                ->with($code)
                ->willReturn(!$codeUsed);

            if ($codeUsed === false) {
                $conference->expects($this->at(1))
                    ->method('getDiscountForCode')
                    ->willReturn($codeDiscount);

                $conference->expects($this->at(2))
                    ->method('markCodeAsUsed')
                    ->with($code);
            }

        }

        $discount = $discountService->calculate($conferenceId, $attendantsCount, $price, $code, $discountsTypes);

        $this->assertSame($expectedDiscount, $discount);
    }

    public function discountsDataProvider()
    {
        return [
            [1, 3, 3000, ['group'], [5 => 10, 10 => 20], null, null, null, 0.0],
            [1, 6, 6000, ['group'], [5 => 10, 10 => 20], null, null, null, 600.0],
            [1, 10, 10000, ['group'], [5 => 10, 10 => 20], null, null, null, 2000.0],
            [1, 1, 1000, ['code'], null, 'abc123', false, ['money', 150], 150.0],
            [1, 1, 1000, ['code'], null, 'abc123', false, ['percent', 50], 500.0],
            [1, 1, 1000, ['code'], null, 'qwe987', true, null, 0.0],
            [1, 7, 7000, ['group', 'code'], [5 => 10, 10 => 20], 'abc123', false, ['money', 150], 700.0],
        ];
    }
}
