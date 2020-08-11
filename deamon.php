<?php
require_once __DIR__ . '/vendor/autoload.php';

//Необходимые классы
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$MEBEL_OPTOVIK_ID = $_ENV['MEBEL_OPTOVIK_ID'] ?? '';
$RABBIT_HOST = $_ENV['RABBIT_HOST'] ?? '';
$RABBIT_PORT = $_ENV['RABBIT_PORT'] ?? '';
$RABBIT_LOGIN = $_ENV['RABBIT_LOGIN'] ?? '';
$RABBIT_PSWD = $_ENV['RABBIT_PSWD'] ?? '';
$RABBIT_CHANNEL_NAME = $_ENV['RABBIT_CHANNEL_NAME'] ?? '';
$RABBIT_V_HOST = $_ENV['RABBIT_V_HOST'] ?? '';

/**
 * пример работы с гугл таблицами https://codd-wd.ru/primery-google-sheets-tablicy-api-php/
 */

//Подключаемся к сервису гугл таблиц
$googleAccountKeyFilePath = __DIR__ . '/bender-oauth.json';
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $googleAccountKeyFilePath);
$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->addScope('https://www.googleapis.com/auth/spreadsheets');
$service = new Google_Service_Sheets($client);

//получение заголовков таблицы
$data = $service->spreadsheets_values->get($MEBEL_OPTOVIK_ID, "Заявки!B1:P1");
if (empty($data->values)) exit;
$headers = $data->values[0];

//получение строки значений
$data = $service->spreadsheets_values->get($MEBEL_OPTOVIK_ID, "Заявки!B2:P2");
if (empty($data->values)) exit;
$values = $data->values[0];

//формирование сообщения
$message = ['type' => 'google_sheets', 'data' => []];
$data = ['site' => 'МебельОптовик'];
foreach ($headers as $key => $head) {
	$data[$head] = $values[$key];
}
$message['data'] = $data;

try {
	//Создание подключение к очереди
	$connection = new AMQPStreamConnection($RABBIT_HOST, $RABBIT_PORT, $RABBIT_LOGIN, $RABBIT_PSWD, $RABBIT_V_HOST);
	$channel = $connection->channel();
	$channel->queue_declare($RABBIT_CHANNEL_NAME, false, true, false, false);
	
	//создаем сообщение в очередь
	$msg = new AMQPMessage(json_encode($message, JSON_UNESCAPED_UNICODE));
	$channel->basic_publish($msg, '', $RABBIT_CHANNEL_NAME);
	$channel->close();
	$connection->close();
} catch (\Throwable $th) {
	echo (json_encode($th->getMessage(), JSON_UNESCAPED_UNICODE));
	exit;
}
echo $message;
// Удаление обработанной строки
$requests = [
	new Google_Service_Sheets_Request([
		'deleteRange' => [
			'range' => [
				'sheetId' => 660544691,
				'startRowIndex' => 1,
				'endRowIndex' => 2,
			],
			'shiftDimension' => 'ROWS'
		]
	])
];
$batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
	'requests' => $requests
]);
$service->spreadsheets->batchUpdate($MEBEL_OPTOVIK_ID, $batchUpdateRequest);
exit;