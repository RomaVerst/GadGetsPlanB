<?php

use Bitrix\Main\Localization\Loc;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$arDescription = [
    "NAME" => Loc::getMessage('PB_GADGETS_NAME'),
    "DESCRIPTION" => Loc::getMessage('PB_GADGETS_DESC'),
    "ICON" => "",
    "TITLE_ICON_CLASS" => "bx-gadgets",
    "GROUP" => ["ID" => "admin_content"],
    "NOPARAMS" => "Y",
    "AI_ONLY" => true,
    "IBLOCK_ONLY" => true,
];
