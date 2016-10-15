<?php
/*
Plugin Name: WP Russian Horoscope
Plugin URI: https://wordpress.org/plugins/wp-russian-horoscope/
Description: Плагин для отображения ежедневного гороскопа по категориям.
Version: 1.1
Author: Glaswr
Author URI: https://www.fl.ru/users/Glaswr/
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class ruhoroscope {
	protected $types;		// Типы гороскопов
	protected $marks;		// Знаки зодиака
	protected $dateUpdate;	// Дата последнего обновления
	protected $data;		// Данные горосокопов

	function __construct() {
		$this->types = array(
			'bn'		=>	'Общий',
			'love'		=>	'Любовный',
			'mob'		=>	'Мобильный',
			'auto'		=>	'Автомобильный',
			'cook'		=>	'Кулинарный',
			'biz'		=>	'Финансовый',
			'bn_tour'	=>	'Туристкий',
			'bn_mon'	=>	'На месяц',
			'bn_year'	=>	'На год',
			);

		$this->marks = array(
			'aries' 		=> 'Овен', 
			'aurus' 		=> 'Телец', 
			'gemini' 		=> 'Близнецы', 
			'cancer' 		=> 'Рак', 
			'leo' 			=> 'Лев', 
			'virgo' 		=> 'Дева', 
			'libra' 		=> 'Весы', 
			'scorpio' 		=> 'Скорпион', 
			'sagittarius' 	=> 'Стрелец', 
			'capricorn' 	=> 'Козерог', 
			'aquarius' 		=> 'Водолей', 
			'pisces' 		=> 'Рыбы',
			);

		$this->dateUpdate 	= get_option('wp-ruhoroscope-dateUpdate');
		$this->data 		= get_option('wp-ruhoroscope-data');

		if (($this->dateUpdate == 0 && $this->data == 0) || (strtotime(date('Y-m-d')) > $this->dateUpdate))  {
			$this->updateData();
		}

		add_shortcode('ruhoroscope', array($this, 'shortcodeHoroscope'));
		add_action('admin_menu', array($this, 'menuPluginHoroscope'));
	}

	/**
	 * Получаем код страницы путём cURL
	 * @param  string $url Ссылка на страницу
	 * @return string      HTML код полученой старинцы
	 */
	function getPageCode($url) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

		$pageCode = curl_exec($ch); 

		curl_close($ch);

		return $pageCode;
	}

	/**
	 * Получает XML страницу и возвращает JSON массив
	 * @param  string $url Ссылка на поток XML
	 * @return json        Данные из XML
	 */
	function parseXML($url) {
		$pageCode = $this->getPageCode($url);
		$pageCode = str_replace(array("\n", "\r", "\t"), '', $pageCode);
		$pageCode = trim(str_replace('"', "'", $pageCode));
		$xml      = simplexml_load_string($pageCode);
		$json     = json_encode($xml);

		return $json;
	}

	/**
	 * Определяет ID типа
	 * @param  string $string Строка содержащая текст для поиска
	 * @return string         ID текущего типа
	 */
	function getTypesHoroscope($string) {
		foreach ($this->types as $id => $name) {
			$needType 		= mb_strtolower($name, 'utf8');
			$searchString 	= mb_strtolower($string, 'utf8');

			if (stristr($searchString, $needType)) {
				return $id;
			}
		}

		return false;
	}

	/**
	 * Определяет ID гороскопа
	 * @param  string $string Строка содержащая текст для поиска
	 * @return string         ID текущего гороскопа
	 */
	function getMarksHoroscope($string) {
		foreach ($this->marks as $id => $name) {
			$needMark 		= mb_strtolower($name, 'utf8');
			$searchString 	= mb_strtolower($string, 'utf8');

			if (stristr($searchString, $needMark)) {
				return $id;
			}
		}

		return false;
	}

	/**
	 * Получает гороскоп по определённому типу
	 * @param  string $type Идетификатор типа
	 * @return array   		Массив данных гороскопа
	 */
	function getHoroscope($type) {
		$resultData 			= array();
		$currentHoroscopeJson 	= $this->parseXML('http://www.hyrax.ru/cgi-bin/'.$type.'_xml.cgi');
		$currentHoroscope 		= json_decode($currentHoroscopeJson, true);
		$currentHoroscopeNeed 	= $currentHoroscope['channel']['item'];

		foreach ($currentHoroscope['channel']['item'] as $horoscopeData) {
			$key = $this->getMarksHoroscope($horoscopeData['title']);
			$resultData[$key] = $horoscopeData['description'];
		}

		return $resultData;
	}

	/**
	 * Получает все гороскопы
	 * @return array Массив данных гороскопа
	 */
	function getHoroscopeAll() {
		$resultData = array();
		
		foreach ($this->types as $id => $name) {
			$resultData[$id] = $this->getHoroscope($id);
		}
		
		return $resultData;
	}

	/**
	 * Обновление кеша данных
	 * @return null
	 */
	function updateData() {
		$newHoroscope 	= json_encode($this->getHoroscopeAll());
		$newDate 		= strtotime(date('Y-m-d'));

		update_option('wp-ruhoroscope-dateUpdate', $newDate);
		update_option('wp-ruhoroscope-data', $newHoroscope);
	}

	/**
	 * Создание шорткода
	 * @param  array $atts Параметры шорткода
	 * @return html        HTML код шорткода
	 */
	function shortcodeHoroscope($atts) {
		extract(shortcode_atts(array(
			'type' => 'Общий',
			'mark' => 'Овен'
			), $atts));

		$currentType = $this->getTypesHoroscope($type);
		$currentMark = $this->getMarksHoroscope($mark);
		$data = json_decode($this->data, true);
		$horoscope = $data[$currentType][$currentMark];

		return $horoscope;
	}

	/**
	 * Добавляем пункты в адмип панель
	 * @return null
	 */
	function menuPluginHoroscope() {
		add_submenu_page('options-general.php', 'Гороскоп', 'Гороскоп', 'manage_options', 'ruhoroscope', array($this, 'indexPageHoroscope')); 
	}

	/**
	 * Выводим страницу в админ панеле 
	 * @return html HTML код страницы
	 */
	function indexPageHoroscope() {
		if (!current_user_can('manage_options')){
			wp_die( __('У вас нет достаточных прав для доступа к этой странице.') );
		}

		?>
		<div class="wrap">
			<h2 style="text-align:center;width: 574px;">Плагин WP Russian Horoscope v1.1 by Glaswr</h2>

			<div class="card">
				<h3>Пожертвование</h3>
				<p>Здравствуйте, спасибо что выбрали именно мой плагин для отображения гороскопов у себя на сайте. Если вы по-настоящему цените мой труд, то пожертвуйте небольшую сумму для того, чтобы я поддерживал и дальше разработку этого плагина. Спасибо 😊</p>
				<iframe style="margin: 0px auto;display: block;" frameborder="0" allowtransparency="true" scrolling="no" src="https://money.yandex.ru/embed/donate.xml?account=410013333802848&quickpay=donate&payment-type-choice=on&mobile-payment-type-choice=on&default-sum=50&targets=%D0%9F%D0%BE%D0%B4%D0%B4%D0%B5%D1%80%D0%B6%D0%BA%D0%B0+%D0%B2+%D1%81%D0%BE%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B8+%D0%BF%D0%BB%D0%B0%D0%B3%D0%B8%D0%BD%D0%BE%D0%B2&target-visibility=on&project-name=&project-site=&button-text=05&successURL=" width="508" height="90"></iframe>
			</div>

			<div class="card">
				<h3>Общая информация <span style="float:right">Гороскоп от: <?php echo date('d.m.Y', get_option('wp-ruhoroscope-dateUpdate')); ?></span></h3>
				<p>Данный плагин отображает актуальный на текущую дату гороскоп по нескольким категориям. Которые вы можете регулировать и выводить информацию в шорткоде.</p>
				<h3>Категории</h3>
				<ul>
					<li><b>Общий</b> - общая характеристика дня основанная на лунном календаре и прогнозы</li>
					<li><b>Любовный</b> - любовный гороскоп</li>
					<li><b>Мобильный</b> - мобильный гороскоп для владельцев мобильных телефонов</li>
					<li><b>Автомобильный</b> - юмористический гороскоп для владельцев автомобилей</li>
					<li><b>Кулинарный</b> - кулинарный гороскоп </li>
					<li><span class="description" style="color: #F44336;">new</span> <b>Финансовый</b> - финансовый гороскоп</li>
					<li><span class="description" style="color: #F44336;">new</span> <b>Туристкий</b> - туристкий гороскоп</li>
					<li><span class="description" style="color: #F44336;">new</span> <b>На месяц</b> - гороскоп на месяц (Обновляется раз в неделю)</li>
					<li><span class="description" style="color: #F44336;">new</span> <b>На год</b> - гороскоп на год (Обновляется раз в год)</li>
				</ul>
				<h3>Структура шорткодов</h3>
				<p><code>[ruhoroscope type="%TYPE%" mark="%MARK%"]</code><br><br>
					<b>%TYPE%</b> - Тип гороскопа. Например: <i>Любовный</i> <br>
					<b>%MARK%</b> - Знак гороскопа. Например: <i>Бизнецы</i>
				</p>
				<h3>Генератор шорткодов</h3>
				<div style="text-align:center">
					<select class="postform" id="type" onchange="gen_code()">
						<option value="Общий" selected="selected">Общий</option>
						<option value="Любовный">Любовный</option>
						<option value="Мобильный">Мобильный</option>
						<option value="Автомобильный">Автомобильный</option>
						<option value="Кулинарный">Кулинарный</option>
						<option value="Финансовый">Финансовый</option>
						<option value="Туристкий">Туристкий</option>
						<option value="На месяц">На месяц</option>
						<option value="На год">На год</option>
					</select>

					<select class="postform" id="mark" onchange="gen_code()">
						<option value="Овен" selected="selected">Овен</option>
						<option value="Телец">Телец</option>
						<option value="Близнецы">Близнецы</option>
						<option value="Рак">Рак</option>
						<option value="Лев">Лев</option>
						<option value="Дева">Дева</option>
						<option value="Весы">Весы</option>
						<option value="Скорпион">Скорпион</option>
						<option value="Стрелец">Стрелец</option>
						<option value="Козерог">Козерог</option>
						<option value="Водолей">Водолей</option>
						<option value="Рыбы">Рыбы</option>
					</select>
					<br>
					<p id="code" style="padding-top:5px;"><code>[ruhoroscope type="Общий" mark="Овен"]</code></p>
				</div>
				<h3>Написать создателю</h3>
				Skype - <a href="skype:glaswr">Glaswr</a><br>
				Email - <a href="mailto:glaswr@yandex.ru">glaswr@yandex.ru</a><br>
				FL - <a href="https://www.fl.ru/users/Glaswr/">Freelance</a> 
			</div>
		</div>
		<script>
			function gen_code() {
				var type, mark;
				type = document.getElementById("type").options[document.getElementById("type").selectedIndex].text;
				mark = document.getElementById("mark").options[document.getElementById("mark").selectedIndex].text;
				document.getElementById("code").innerHTML="<code>[ruhoroscope type=\""+type+"\" mark=\""+mark+"\"]</code>";
			}
		</script>
		<?php
	}
}

$ruhoroscope = new ruhoroscope;
?>