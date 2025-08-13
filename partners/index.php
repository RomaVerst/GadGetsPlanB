<?php

use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc,
    Bitrix\Main\Type\DateTime,
    Bitrix\Main\Data\Cache,
    Bitrix\Main\Application;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @global CMain $APPLICATION */
global $APPLICATION;

if (!Loader::includeModule('iblock')) {
    return;
}

if (empty($arGadgetParams['IBLOCK_ID'])) {
    echo Loc::getMessage('PB_GADGETS_IBLOCK_ID_NOT_FOUND');
    return;
}

$APPLICATION->SetAdditionalCSS('/bitrix/gadgets/planb/partners/styles.css');

// Закэшируем данные в тегированный кэш, чтобы кэш менялся при обновлении элементов инфоблока

$cache = Cache::createInstance();
$taggedCache = Application::getInstance()->getTaggedCache();

$cachePath = $cacheKey = 'pb_requests_partners';
$cacheTtl = 36000;

if ($cache->initCache($cacheTtl, $cacheKey, $cachePath)) {
    $partnersRequests = $cache->getVars();
} elseif ($cache->startDataCache()) {

    $iblockClassRequests = \Bitrix\Iblock\Iblock::wakeUp($arGadgetParams['IBLOCK_ID'])->getEntityDataClass();
    $taggedCache->startTagCache($cachePath);
    $currentMonthStart = DateTime::createFromPhp(new \DateTime(date('Y-m-01 00:00:00')));
    $currentMonthEnd   = DateTime::createFromPhp(new \DateTime(date('Y-m-t 23:59:59')));

    // Выбираем все данные из инфоблока за текущий месяц, включая значения списочных свойств
    $items = $iblockClassRequests::getList([
        'select' => [
            'ID',
            'NAME',
            'SORT',
            'DATE_CREATE',
            'STAT_IS_LEGAL_' => 'STAT_IS_LEGAL',
            'STATUS_ID_' => 'STATUS',
            'STATUS_REQUEST_VALUE' => 'STATUS_REQUEST.VALUE',
            'LEGAL_STATUS_VALUE' => 'LEGAL_STATUS.VALUE',
            'IBLOCK_ID'
        ],
        'filter' => [
            '>=DATE_CREATE' => $currentMonthStart,
            '<=DATE_CREATE' => $currentMonthEnd,
        ],
        'runtime' => [
            'STATUS_REQUEST' => [
                'data_type' => '\Bitrix\Iblock\PropertyEnumerationTable',
                'reference' => [
                    '=this.STATUS.VALUE' => 'ref.ID',
                ],
                'join_type' => 'left'
            ],
            'LEGAL_STATUS' => [
                'data_type' => '\Bitrix\Iblock\PropertyEnumerationTable',
                'reference' => [
                    '=this.STAT_IS_LEGAL.VALUE' => 'ref.ID',
                ],
                'join_type' => 'left'
            ],
        ],
        'order' => ['SORT' => 'ASC'],
    ])->fetchAll();

    $partnersRequests = [];

    foreach ($items as $item) {
        $requestKeyInit = ($item['STATUS_REQUEST_VALUE'] === 'success')
            ? 'OPEN' : 'CLOSED';
        $requestKeyInit .= (!empty($item['LEGAL_STATUS_VALUE']) && $item['LEGAL_STATUS_VALUE'] === 'Y')
            ? '%LEGAL' : '%INDIVIDUAL';

        $now = new DateTime();

        // Вычисляем объект даты начала недели
        $weekStart = (clone $now)
            ->setTime(0, 0, 0)
            ->add("-" . ($now->format("N") - 1) . " days");

        // Вычисляем объект даты конца недели
        $weekEnd = (clone $weekStart)
            ->add("+6 days")
            ->setTime(23, 59, 59);

        if ($item['DATE_CREATE']->format('Ymd') === $now->format('Ymd')) {
            $partnersRequests[$requestKeyInit . '%CUR_DAY']++;
        }
        if ($item['DATE_CREATE'] >= $weekStart && $item['DATE_CREATE'] <= $weekEnd) {
            $partnersRequests[$requestKeyInit . '%CUR_WEEK']++;
        }
        if ($item['DATE_CREATE']->format('Ym') === $now->format('Ym')) {
            $partnersRequests[$requestKeyInit . '%CUR_MONTH']++;
        }

        $partnersRequests[$requestKeyInit . '%ALL']++;
    }

    // Кеш сбрасывать при изменении данных в инфоблоке с ID $arGadgetParams['IBLOCK_ID']
    $taggedCache->registerTag('iblock_id_' . $arGadgetParams['IBLOCK_ID']);

    $taggedCache->endTagCache();
    $cache->endDataCache($partnersRequests);
}

if (!empty($partnersRequests)):
    ?>
    <div class="bx-gadgets-info plan-b-requests">
        <div class="plan-b-requests__wrapper">
            <div class="item-group __empty"></div>
            <div class="item-group"><?= Loc::getMessage('PB_GADGETS_TODAY') ?></div>
            <div class="item-group"><?= Loc::getMessage('PB_GADGETS_CUR_WEEK') ?></div>
            <div class="item-group"><?= Loc::getMessage('PB_GADGETS_MONTH') ?></div>
            <div class="item-group"><?= Loc::getMessage('PB_GADGETS_ALL') ?></div>
        </div>

        <div class="sub-title"><?= Loc::getMessage('PB_GADGETS_CLOSED_REQUESTS') ?></div>
        <div class="plan-b-requests__wrapper">
            <div class="item-group">
                <div><?= Loc::getMessage('PB_GADGETS_INDIVIDUAL') ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['CLOSED%INDIVIDUAL%CUR_DAY'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['CLOSED%INDIVIDUAL%CUR_WEEK'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['CLOSED%INDIVIDUAL%CUR_MONTH'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['CLOSED%INDIVIDUAL%ALL'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= Loc::getMessage('PB_GADGETS_LEGAL') ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['CLOSED%LEGAL%CUR_DAY'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['CLOSED%LEGAL%CUR_WEEK'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['CLOSED%LEGAL%CUR_MONTH'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['CLOSED%LEGAL%ALL'] ?? 0 ?></div>
            </div>
        </div>
        <div class="sub-title"><?= Loc::getMessage('PB_GADGETS_OPEN_REQUESTS') ?></div>
        <div class="plan-b-requests__wrapper">
            <div class="item-group">
                <div><?= Loc::getMessage('PB_GADGETS_INDIVIDUAL') ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['OPEN%INDIVIDUAL%CUR_DAY'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['OPEN%INDIVIDUAL%CUR_WEEK'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['OPEN%INDIVIDUAL%CUR_MONTH'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['OPEN%INDIVIDUAL%ALL'] ?? 0 ?></div>
            </div>

            <div class="item-group">
                <div><?= Loc::getMessage('PB_GADGETS_LEGAL') ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['OPEN%LEGAL%CUR_DAY'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['OPEN%LEGAL%CUR_WEEK'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['OPEN%LEGAL%CUR_MONTH'] ?? 0 ?></div>
            </div>
            <div class="item-group">
                <div><?= $partnersRequests['OPEN%LEGAL%ALL'] ?? 0 ?></div>
            </div>
        </div>
    </div>
<?php
endif;

