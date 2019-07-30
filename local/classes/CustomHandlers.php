<?php
/** @global \CDatabase $DB */
/** @global \CUser $USER */
/** @global \CMain $APPLICATION */
/** @global Site $_site */

namespace FLXMD;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Mail\Event;

class CustomHandlers
{
	// example www.glivi.by
	public static function setMetaLinkAttr()
	{
		if (!defined('ADMIN_SECTION')) {

			global $_site;
			$siteHost = $_site->_server->get('HTTP_X_FORWARDED_PROTO');
			$requestUri = $_site->_server->get('REQUEST_URI');
			$serverName = $_site->_server->get('SERVER_NAME');

			// проверяем моб версия сайта сейчас или нет
			if (preg_match('/^[m.]{2}/', $serverName)) {

				//  проверяем есть ли 200 ответ сервера на моб версии сайта

				$url = $siteHost . '://www.glivi.by' . $requestUri;

				$handle = curl_init($url);
				curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
				$response = curl_exec($handle);

				$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE); // здесь находится ответ сервера

				if ($httpCode !== 404) {
					// вставляем нужные аттрибуты
					$stUrls = '<link rel="canonical" href="' . $url . '" />' . " ";
				}

			} else {

				//  проверяем есть ли 200 ответ сервера на моб версии сайта

				$url = $siteHost . '://m.glivi.by' . $requestUri;

				$handle = curl_init($url);
				curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
				$response = curl_exec($handle);

				$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE); // здесь находится ответ сервера

				if ($httpCode !== 404) {
					// вставляем нужные аттрибуты
					$stUrls = '<link rel="alternate" href="' . $url . '" />' . " ";
				}

			}

			curl_close($handle);
			return $stUrls;

		}
	}

	// add seo-links <link rel="prev"...>, <link rel="next"...>, <title name="example_Страница- 1">
	// example krea.by
	public static function AddSeoLinks(&$content)
	{
		global $APPLICATION;
		$arFindNum = array();
		$arHeadPage = array();
		// хост с добавлением протокола и без порта
		$stHttpProt = (CMain::IsHTTPS()) ? "https://" : "http://";
		$stServerHost = $stHttpProt . preg_replace('/:[0-9]{1,}/', "", $_SERVER['HTTP_HOST']);
		// теги в <head>
		preg_match("/(?P<beforehead>(.*))<head>/is", $content, $arBeforeHeadPage);
		preg_match("/<head>(?P<headPage>(.*))<\/head>/is", $content, $arHeadPage);
		preg_match("/<\/head>(?P<afterhead>(.*))/is", $content, $arAfterHeadPage);
		if (!empty($arBeforeHeadPage["beforehead"]) && !empty($arAfterHeadPage["afterhead"]) && !empty($arHeadPage["headPage"])) {
			$stBeforehead = $arBeforeHeadPage["beforehead"];
			$headPage = $arHeadPage["headPage"];
			$stAfterhead = $arAfterHeadPage["afterhead"];
		}

		//Проверка на 404 страницу
		if (defined("ERROR_404") != true) {
			if ($content != false && strpos($content, '<!-- has_stranation_pagen') != false) {
				// выбираем из get-строки NavNum
				preg_match('/(<!-- has_stranation_pagen_)(?P<num>[0-9]{1,})?/i', $content, $arFindNum);
				if (!empty($arFindNum['num'])) {
					$stPageNum = "PAGEN_" . $arFindNum['num'];
				}

				// выбираем из get-строки NavPageNomer
				$nCurrentPage = 1;
				if (isset($_GET[$stPageNum]) && (int)$_GET[$stPageNum] > 1) {
					$nCurrentPage = (int)$_GET[$stPageNum];
				}

				//формируем url предыдущей страницы <link rel="prev" ...>
				$stUrl = $stServerHost . $APPLICATION->GetCurUri("", false);
				if ($nCurrentPage > 1) {
					if ($nCurrentPage == 2) {
						$stUrlPrev = preg_replace("/$stPageNum=$nCurrentPage/i", "", $stUrl);
						//очистка на случай, если останется последний символ ? или &
						$stUrlPrev = trim($stUrlPrev, '?&');
					} else {
						$nPrevPage = $nCurrentPage - 1;
						$stUrlPrev = preg_replace("/$stPageNum=$nCurrentPage/i", "$stPageNum=$nPrevPage", $stUrl);
					}
				}

				//формируем url следующей страницы для <link rel="next" ...>
				if (strpos($content, '<!-- it_last_page_stranation -->') == false) {
					$nNextPage = $nCurrentPage + 1;
					if($nCurrentPage == 1) {
						$stUrlNext = $stServerHost . $APPLICATION->GetCurUri("$stPageNum=$nNextPage", false);
					} else {
						$stUrlNext = preg_replace("/$stPageNum=$nCurrentPage/i", "$stPageNum=$nNextPage", $stUrl);
					}
				}

				// добавляем в <head>
				if ($stUrlPrev) $stUrls = '<link rel="prev" href="' . $stUrlPrev . '" />' . " ";
				if ($stUrlNext) $stUrls .= '<link rel="next" href="' . $stUrlNext . '" />';
				$content = $stBeforehead . "<head>" . $headPage . $stUrls . "</head>" . $stAfterhead;
				//AddMessage2Log($content);

				// при пагинации добавление в <title> куска " Страница - n"
				if ($nCurrentPage != 1) {
					preg_match("/<title>(?P<title>.*)<\/title>/is", $content, $stOldTitle);
					$content = preg_replace("/<title>(.*)<\/title>/is", "<title>" . $stOldTitle["title"] . " Страница - " . $nCurrentPage . "</title>", $content);
				}

			}
		}
	}

	// example inmodels.by
	public static function redirectLangVersion()
	{
		global $_site;
		$siteHost = $_site->_server->get('HTTP_X_FORWARDED_PROTO');
		$requestUri = $_site->_server->get('REQUEST_URI');
		$serverName = $_site->_server->get('SERVER_NAME');
		$session = $_SESSION['check_first_coming'];

		preg_match('/(?<id>^ru)/',  $_site->_server->get('HTTP_ACCEPT_LANGUAGE'), $rus);
		preg_match('/(?<id>^uk)/',  $_site->_server->get('HTTP_ACCEPT_LANGUAGE'), $uk);

		if (
			$uk['id'] ||
			$rus['id'] && $session !== 'Y'
		) {
			$_SESSION['check_first_coming'] = 'Y';

			if (!preg_match('/^\/ru\/{1}/', $requestUri)) {
				header('Location: '. $siteHost .'://'. $serverName .'/ru' . $requestUri);
			}

		}
	}



}
