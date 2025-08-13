<?php

use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc,
    Bitrix\Iblock\TypeTable,
    Bitrix\Iblock\IblockTable;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!Loader::includeModule('iblock')) {
    return;
}

// Параметры для удобства задания ID инфоблока заявок

$ibTypes = [];
$iblocks = [];

$dbTypes = TypeTable::getList([
    'select' => ['ID', 'NAME' => 'LANG_MESSAGE.NAME'],
    'filter' => ['=LANG_MESSAGE.LANGUAGE_ID' => LANGUAGE_ID],
    'order' => ['SORT' => 'ASC'],
    'cache' => ['ttl' => 3600]
]);
while ($type = $dbTypes->Fetch()) {
    $ibTypes[$type['ID']] = '[' . $type["ID"] . '] ' . $type['NAME'];
}

$arParameters = [
    "PARAMETERS" => [],
    "USER_PARAMETERS" => [
        "IBLOCK_TYPE" => [
            "NAME" => Loc::getMessage("PB_GADGETS_IBLOCK_TYPE"),
            "TYPE" => "LIST",
            "VALUES" => $ibTypes,
            "MULTIPLE" => "N",
            "DEFAULT" => array_key_first($ibTypes),
            "REFRESH" => "Y"
        ]
    ]
];

if (
    is_array($arAllCurrentValues)
    && array_key_exists('IBLOCK_TYPE', $arAllCurrentValues)
    && array_key_exists('VALUE', $arAllCurrentValues['IBLOCK_TYPE'])
    && !empty($arAllCurrentValues['IBLOCK_TYPE']['VALUE'])
) {

    $dbIblocks = IblockTable::getList([
        'filter' => [
            'IBLOCK_TYPE_ID' => $arAllCurrentValues['IBLOCK_TYPE']['VALUE'],
        ],
        'select' => ['ID', 'NAME',],
        'cache' => ['ttl' => 3600]
    ]);

    while ($iblock = $dbIblocks->Fetch()) {
        $iblocks[$iblock['ID']] = '[' . $iblock["ID"] . '] ' . $iblock['NAME'];
    }

}

if (count($iblocks) > 0) {
    $arParameters["USER_PARAMETERS"]["IBLOCK_ID"] = [
        "NAME" => Loc::getMessage("PB_GADGETS_IBLOCK_ID"),
        "TYPE" => "LIST",
        "VALUES" => $iblocks,
        "MULTIPLE" => "N",
        "DEFAULT" => array_key_first($iblocks),
        "REFRESH" => "Y"
    ];
}