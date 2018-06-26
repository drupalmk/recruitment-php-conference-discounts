<?php

namespace RstGroup\Recruitment\ConferenceSystem\Discount;

use RstGroup\Recruitment\ConferenceSystem\Conference\ConferenceRepository;

// Kod metody calculate jest ciężki do ogarnięcia na pierwszy rzut ok.
// Jest tam switch case, co sugeruje, że obsługę typu discountu można by wyciągnąć do osobnego obiektu.
// To znaczy stworzyć interface DiscountCalculatorInterface z metodą calculate z dwiema klasami go implementującymi:
//  GroupDiscountCalculator i CodeDiscountCalculator.
class DiscountService
{
    protected $allDiscounts = [
        'group', 'code'
    ];
    
    //1. Nie ma opcji żeby metoda calculate była odpowiedzialna za pobieranie obiektu klasy Conference.
    //2. Właściwie metoda nie powinna wiedzieć o obiekcie Conference
    //3. Potrzebna refaktoryzacja, metoda robi za dużo i przyjmuje za dużo argumentów.
    //4. Brak dokumentacji metody, zapewne celowy na potrzeby zadania.
    //5. Argument $attendantsCount powinien być dostęny w klasie Conference, więc na pierwszy rzut oka wydaje się zbędny.
    //6. Argument $price jak w punkcie 5. Jeśli dobrze rozumiem, że $price to koszt konferencji?
    //7. W przypadku refaktoryzacji do osobnych obiektów wspomnianych w początkowym komentarzu, argument $discountsTypes - zbędny.
    //8. Dwa ostatnie argumenty - relikt przeszłości wg mnie, do refaktoryzacji na odpowiedni podtyp klasy Exception.
    public function calculate($conferenceId, $attendantsCount = null, $price = null, $discountCode = null, $discountsTypes = [], &$is_error = false, &$error_message = null)
    {
        if (empty($discountsTypes)) {
            $discountsToProcess = $this->allDiscounts;
        } else {
            $discountsToProcess = array_intersect($this->allDiscounts, $discountsTypes);
        }

        $totalDiscount = 0;
        $excludeCodeDiscount = false;

        foreach ($discountsToProcess as $discount) {
            switch ($discount) {
                case 'group':
                    $conference = $this->getConferencesRepository()->getConference($conferenceId);

                    if ($conference === null) {
                        throw new \InvalidArgumentException(sprintf("Conference with id %s not exist", $conferenceId));
                    }

                    $groupDiscount = $conference->getGroupDiscount();

                    if (!is_array($groupDiscount)) {
                        //Tutaj powinien być wyrzucony wyjątek. Oba parametry metody, wg. mnie zupełnie zbędne.
                        $is_error = true;
                        $error_message = 'Error';
                        return;
                    }

                    $matchingDiscountPercent = 0;

                    foreach ($groupDiscount as $minAttendantsCount => $discountPercent) {
                        if ($attendantsCount >= $minAttendantsCount) {
                            $matchingDiscountPercent = $discountPercent;
                        }
                    }
                    //Dla czystości kodu wywaliłbym to do jakieś prywatnej metody.
                    $totalDiscount += $price * (float)"0.{$matchingDiscountPercent}";

                    $excludeCodeDiscount = true;

                    break;
                case 'code':
                    if ($excludeCodeDiscount == true) {
                        continue;
                    }
                    //Powtórzenie kodu, którego w mojej opinii w ogóle nie powinna tu być.
                    $conference = $this->getConferencesRepository()->getConference($conferenceId);

                    if ($conference === null) {
                        throw new \InvalidArgumentException(sprintf("Conference with id %s not exist", $conferenceId));
                    }

                    if ($conference->isCodeNotUsed($discountCode)) {
                        list($type, $discount) =  $conference->getDiscountForCode($discountCode);

                        if ($type == 'percent') {
                            $totalDiscount += $price * (float)"0.{$discount}";
                        } else if ($type == 'money') {
                            $totalDiscount += $discount;
                        } else {
                            //Tutaj powinien być wyrzucony wyjątek. Oba parametry metody, wg. mnie zupełnie zbędne.
                            $is_error = true;
                            $error_message = 'Error';
                            return;
                        }

                        $conference->markCodeAsUsed($discountCode);
                    }

                    break;
            }
        }

        return (float)$totalDiscount;
    }
    //ConferenceRepository powinno być wstrzykiwane do DiscountService
    //W ogóle DiscountService powinien wiedzieć tylko o obiekcie Conference, a nie do ConferenceRepository.
    protected function getConferencesRepository()
    {
        return new ConferenceRepository();
    }
}
