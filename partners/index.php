<?php

use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc,
    Bitrix\Main\Type\DateTime,
    Bitrix\Main\Data\Cache,
    Bitrix\Main\Application,
    Bitrix\Iblock\PropertyTable,
    Bitrix\Iblock\ElementPropertyTable,
    Bitrix\Iblock\PropertyEnumerationTable,
    Bitrix\Highloadblock\HighloadBlockTable;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @global CMain $APPLICATION */
global $APPLICATION;

if (!Loader::includeModule('iblock')) {
    return;
}
if (!Loader::includeModule('highloadblock')) {
    return;
}

if (empty($arGadgetParams['IBLOCK_TYPE'])) {
    echo Loc::getMessage('PB_GADGETS_IBLOCK_TYPE_NOT_FOUND');
    return;
}
if (empty($arGadgetParams['IBLOCK_ID'])) {
    echo Loc::getMessage('PB_GADGETS_IBLOCK_ID_NOT_FOUND');
    return;
}

//$APPLICATION->SetAdditionalCSS('/local/gadgets/planb/prog8/styles.css');
$APPLICATION->SetAdditionalCSS('/bitrix/gadgets/planb/partners/styles.css');

$cache = Cache::createInstance();

$cachePath = $cacheKey = 'pb_requests_partners';
$cacheTtl = 36000;

// Для ссылок нам нужны id статусов в hl блоке, поэтому выберем их и запишем в кеш
if ($cache->initCache($cacheTtl, "pb_requests_hl_elements")) {
    $hlElements = $cache->getVars();
} elseif ($cache->startDataCache()) {
    $hlblock = HighloadBlockTable::getList([
        'filter' => ['=NAME' => $arGadgetParams['HL_NAME'] ?? 'Financestatuses']
    ])->fetch();

    if (empty($hlblock)) {
        echo Loc::getMessage('PB_GADGETS_HL_NOT_FOUND');
    }

    $entity = HighloadBlockTable::compileEntity($hlblock);
    $dataClass = $entity->getDataClass();
    $hlElements = [];

    $hlDb = $dataClass::getList([
        'select' => ['ID', 'UF_XML_ID']
    ]);
    while ($hlElement = $hlDb->fetch()) {
        $hlElements[$hlElement['UF_XML_ID']] = $hlElement['ID'];
    }

    $cache->endDataCache($hlElements);
}

if (empty($hlElements)) {
    echo Loc::getMessage('PB_GADGETS_HL_ELEMENTS_NOT_FOUND');
    return;
}

// Выбираем все данные из инфоблока за текущий месяц и запишем в кеш
// Закешируем данные в тегированный кеш, чтобы кеш менялся при обновлении элементов инфоблока
$taggedCache = Application::getInstance()->getTaggedCache();
if ($cache->initCache($cacheTtl, $cacheKey, $cachePath)) {
    $partnersInfo = $cache->getVars();
} elseif ($cache->startDataCache()) {

    $iblockClassRequests = \Bitrix\Iblock\Iblock::wakeUp($arGadgetParams['IBLOCK_ID'])->getEntityDataClass();
    $taggedCache->startTagCache($cachePath);
    $currentMonthStart = DateTime::createFromPhp(new \DateTime(date('Y-m-01 00:00:00')));
    $currentMonthEnd = DateTime::createFromPhp(new \DateTime(date('Y-m-t 23:59:59')));


    // Получаем ID свойств по коду и ID инфоблока
    $props = [];
    $propDb = PropertyTable::getList([
        'filter' => [
            'IBLOCK_ID' => $arGadgetParams['IBLOCK_ID'],
            'CODE' => ['STAT_IS_LEGAL', 'STATUS']
        ],
        'select' => ['ID', 'CODE']
    ]);
    while ($prop = $propDb->fetch()) {
        $props[$prop['CODE']] = $prop['ID'];
    }

    if (empty($props['STAT_IS_LEGAL'])) {
        echo Loc::getMessage('PB_GADGETS_PROPERTY_NOT_FOUND');
        return;
    }

    $items = $iblockClassRequests::getList([
        'select' => [
            'ID',
            'NAME',
            'SORT',
            'DATE_CREATE',
            'STAT_IS_LEGAL_VALUE' => 'STAT_IS_LEGAL_ENUM.VALUE',
            'STATUS_REQUEST_' => 'STATUS',
            'IBLOCK_ID'
        ],
        'filter' => [
            '>=DATE_CREATE' => $currentMonthStart,
            '<=DATE_CREATE' => $currentMonthEnd,
        ],
        'runtime' => [
            'STAT_IS_LEGAL_PROP' => [
                'data_type' => ElementPropertyTable::class,
                'reference' => [
                    '=this.ID' => 'ref.IBLOCK_ELEMENT_ID',
                    '=ref.IBLOCK_PROPERTY_ID' => new \Bitrix\Main\DB\SqlExpression('?i', (int)$props['STAT_IS_LEGAL']),
                ],
                'join_type' => 'left'
            ],
            'STAT_IS_LEGAL_ENUM' => [
                'data_type' => PropertyEnumerationTable::class,
                'reference' => [
                    '=this.STAT_IS_LEGAL_PROP.VALUE_ENUM' => 'ref.ID',
                ],
                'join_type' => 'left'
            ],
        ],
        'order' => ['SORT' => 'ASC'],
    ])->fetchAll();

    $partnersInfo = [
        'PARTNERS_REQUESTS' => [],
        'STATUS_PROPERTY_ID' => $props['STATUS']
    ];

    foreach ($items as $item) {
        $requestKeyInit = ($item['STATUS_REQUEST_VALUE'] === 'success')
            ? 'CLOSED' : 'OPEN';
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
            $partnersInfo['PARTNERS_REQUESTS'][$requestKeyInit . '%CUR_DAY']++;
        }
        if ($item['DATE_CREATE'] >= $weekStart && $item['DATE_CREATE'] <= $weekEnd) {
            $partnersInfo['PARTNERS_REQUESTS'][$requestKeyInit . '%CUR_WEEK']++;
        }
        if ($item['DATE_CREATE']->format('Ym') === $now->format('Ym')) {
            $partnersInfo['PARTNERS_REQUESTS'][$requestKeyInit . '%CUR_MONTH']++;
        }

        $partnersInfo['PARTNERS_REQUESTS'][$requestKeyInit . '%ALL']++;
    }

    // Кеш сбрасывать при изменении данных в инфоблоке с ID $arGadgetParams['IBLOCK_ID']
    $taggedCache->registerTag('iblock_id_' . $arGadgetParams['IBLOCK_ID']);
    $taggedCache->endTagCache();
    $cache->endDataCache($partnersInfo);
}

if (!empty($partnersInfo['PARTNERS_REQUESTS']) && !empty($partnersInfo['STATUS_PROPERTY_ID'])):
    $partnersRequests = $partnersInfo['PARTNERS_REQUESTS'];
    $url = '/bitrix/admin/iblock_list_admin.php?IBLOCK_ID=' . $arGadgetParams['IBLOCK_ID']
        . '&type=' . $arGadgetParams['IBLOCK_TYPE'] . '&lang=' . LANGUAGE_ID
        . '&find_section_section=0&SECTION_ID=0';
    ?>
    <div class="bx-gadgets-info plan-b-requests">
        <div class="plan-b-requests__wrapper">
            <div class="item-group __empty"></div>
            <div class="item-group"><?= Loc::getMessage('PB_GADGETS_TODAY') ?></div>
            <div class="item-group"><?= Loc::getMessage('PB_GADGETS_CUR_WEEK') ?></div>
            <div class="item-group"><?= Loc::getMessage('PB_GADGETS_MONTH') ?></div>
            <div class="item-group"><?= Loc::getMessage('PB_GADGETS_ALL') ?></div>
        </div>

        <div class="sub-title">
            <a href="<?= $url  ?>" class="link-list"
               data-iblock-list="success">
                <?= Loc::getMessage('PB_GADGETS_CLOSED_REQUESTS') ?>
            </a>
        </div>
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
        <div class="sub-title">
            <a href="<?= $url  ?>" class="link-list"
               data-iblock-list="wait,send">
                <?= Loc::getMessage('PB_GADGETS_OPEN_REQUESTS') ?>
            </a>
        </div>
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
    <script>
        // Отправка запроса на изменение фильтра в элементах инфоблока для ссылок
        BX.ready(() => {
            BX.bindDelegate(
                document.body, 'click', {className: 'link-list'},
                function (e) {
                    BX.PreventDefault(e);
                    const link = e.target;
                    let statuses = link.getAttribute('data-iblock-list');
                    if (statuses.length === 0) {
                        return;
                    }

                    BX.ajax({
                        url: '/bitrix/gadgets/planb/partners/ajax.php',
                        // url: '/local/gadgets/planb/prog8/ajax.php',
                        data: {
                            'property': '<?= $partnersInfo['STATUS_PROPERTY_ID'] ?>',
                            'statuses': statuses.split(','),
                            'type': '<?= $arGadgetParams['IBLOCK_TYPE'] ?>',
                            'iblockId': '<?= $arGadgetParams['IBLOCK_ID'] ?>',
                        },
                        method: 'POST',
                        dataType: 'json',
                        onsuccess: function(data){
                            if (data && data.success) {
                                location.href = link.getAttribute('href');
                            } else {
                                console.error('Ошибка ответа', data);
                            }
                        },
                        onfailure: function(error){
                            console.error(error);
                        }
                    });
                }
            );
        })
    </script>
<?php
endif;

