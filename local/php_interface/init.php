<?

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use FLXMD\Site;

require_once(Application::getDocumentRoot() . '/local/vendor/autoload.php');

global $_site;
$_site = new Site;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler('main', 'OnProlog', ['FLXMD\CustomHandlers', 'redirectLangVersion']);
$eventManager->addEventHandler('main', 'OnEndBufferContent', ['FLXMD\CustomHandlers', 'AddSeoLinks']);

?>
