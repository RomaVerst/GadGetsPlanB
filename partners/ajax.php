<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use \Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    LocalRedirect('/');
}

$request = Application::getInstance()->getContext()->getRequest();

$data = $request->getPostList();
$propertyId = (int)$data->get('property') ?? 0;
$iblockId = (int)$data->get('iblockId') ?? 0;
$type = htmlspecialchars(trim($data->get('type'))) ?? '';
$statuses = is_array($data->get('statuses')) ? $data->get('statuses') : [];
$result = ['success' => 0, 'error' => ''];

if (!empty($propertyId) && !empty($iblockId) && !empty($type) && !empty($statuses)) {
    $statuses = array_map('htmlspecialchars', $statuses);
    // Установка фильтра для текущего пользователя
    $setFilter = CUserOptions::SetOption(
        'main.ui.filter',
        'tbl_iblock_list_' . md5("$type.$iblockId"),
        [
            'filters' => [
                'tmp_filter' => [
                    'fields' => [
                        'NAME' => '',
                        'PROPERTY_' . $propertyId => $statuses
                    ],
                    'filter_rows' => 'NAME,PROPERTY_' . $propertyId
                ]
            ]
        ]
    );
    if ($setFilter) {
        $result = ['success' => 1, 'error' => ''];
    } else {
        $result = ['success' => 0, 'error' => Loc::getMessage('PB_GADGETS_AJAX_ERROR_SQL')];
    }

} else {
    $result = ['success' => 0, 'error' => Loc::getMessage('PB_GADGETS_AJAX_ERROR_PARAMETERS')];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
