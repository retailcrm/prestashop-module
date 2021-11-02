<?php

class RetailcrmToolsTest extends RetailcrmTestCase
{
    /**
     * @dataProvider equalCustomerAddresses
     */
    public function testIsEqualCustomerAddress($address1, $address2, $result)
    {
        $this->assertEquals($result, RetailcrmTools::isEqualCustomerAddress($address1, $address2));
    }

    public function equalCustomerAddresses()
    {
        return [
            'Equal addresses' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                true,
            ],
            'Changed phone' => [
                [
                    'phones' => [
                        ['number' => '222'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'Changed index' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '222',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'Reduced address' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                    ],
                ],
                false,
            ],
            'Expanded address' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'Reduced phone' => [
                [
                    'phones' => [
                        ['number' => '111'],
                        ['number' => '222'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'Expanded phone' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '222'],
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'Replaced field' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'region' => 'Region',
                    ],
                ],
                false,
            ],
        ];
    }
}
