<?
function getWeather($lat, $lng, $date, $time) {
	//$date = "20240205";
	//$time = "0500";
	
	$ConvGridGps = new ConvGridGps();
	$gpsToGridData = $ConvGridGps->gpsToGRID($lat, $lng); //WGS
	$gridToGpsData  = $ConvGridGps->gridToGPS($gpsToGridData[x], $gpsToGridData[y]);
	//print_r($gpsToGridData);
	//print_r($gridToGpsData);

	$key = "Your serviceKey";
	$ch = curl_init();
	$url = 'http://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getUltraSrtNcst';
	$queryParams = '?' . urlencode('serviceKey') . '=' . $key;
	$queryParams .= '&' . urlencode('pageNo') . '=' . urlencode('1');
	$queryParams .= '&' . urlencode('numOfRows') . '=' . urlencode('1000');
	$queryParams .= '&' . urlencode('dataType') . '=' . urlencode('JSON');
	$queryParams .= '&' . urlencode('base_date') . '=' . urlencode($date);
	$queryParams .= '&' . urlencode('base_time') . '=' . urlencode($time);
	$queryParams .= '&' . urlencode('nx') . '=' . urlencode($gpsToGridData[x]);
	$queryParams .= '&' . urlencode('ny') . '=' . urlencode($gpsToGridData[y]);

	curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	$response = curl_exec($ch);
	curl_close($ch);
	
	$result =json_decode($response, true);
	//print_r($result);
	if ($result[response][header][resultCode] == "00") {
		$results = $result[response][body][items][item];
		foreach ($results as $item) {
			$data[$item['category']] = $item['obsrValue'];
		}
	}else {
    		$data = $result[response][header];
  	}
	return $data;

	//print_r($data);	
	// Array
	// (
	//     [PTY] => 0	// 강수 형태	없음/비/눈 등 코드
	//     [REH] => 87	// 강수 형태	없음/비/눈 등 코드
	//     [RN1] => 0	// 1시간 동안의 강수량	mm
	//     [T1H] => 1.8	// 기온	°C
	//     [UUU] => -3.2	// 동서방향의 바람 성분	m/s
	//     [VEC] => 53	// 풍향	도
	//     [VVV] => -2.4	// 남북방향의 바람 성분	m/s
	//     [WSD] => 4.1	// 풍속	m/s
	// )
}

//격자좌표계변환
class ConvGridGps {
	const RE = 6371.00877;  // 지구 반경(km)
	const GRID = 5.0;       // 격자 간격(km)
	const SLAT1 = 30.0;     // 투영 위도1(degree)
	const SLAT2 = 60.0;     // 투영 위도2(degree)
	const OLON = 126.0;     // 기준점 경도(degree)
	const OLAT = 38.0;      // 기준점 위도(degree)
	const XO = 43;          // 기준점 X좌표(GRID)
	const YO = 136;         // 기1준점 Y좌표(GRID)

	public static $DEGRAD;
	public static $RADDEG;

	public static $re;
	public static $slat1;
	public static $slat2;
	public static $olon;
	public static $olat;

  public function __construct() {
    self::$DEGRAD = M_PI / 180.0;
    self::$RADDEG = 180.0 / M_PI;
    self::$re = self::RE / self::GRID;
    self::$slat1 = self::SLAT1 * self::$DEGRAD;
    self::$slat2 = self::SLAT2 * self::$DEGRAD;
    self::$olon = self::OLON * self::$DEGRAD;
    self::$olat = self::OLAT * self::$DEGRAD;
  }

	function sn(){
		$snTmp = tan(M_PI * 0.25 + self::$slat2 * 0.5) / tan(M_PI * 0.25 + self::$slat1 * 0.5);
		return log(cos(self::$slat1) / cos(self::$slat2)) / log($snTmp);
	}

	function sf(){
		$sfTmp = tan(M_PI * 0.25 + self::$slat1 * 0.5);
		return pow($sfTmp, $this->sn()) * cos(self::$slat1) / $this->sn();
	}

	function ro(){
		$roTmp = tan(M_PI * 0.25 + self::$olat * 0.5);
		return self::$re * $this->sf() / pow($roTmp, $this->sn());
	}

	function gridToGPS($v1, $v2) {
	  $rs['x'] = $v1;
	  $rs['y'] = $v2;
	  $xn = (int)($v1 - self::XO);
	  $yn = (int)($this->ro() - $v2 + self::YO);
	  $ra = sqrt($xn * $xn + $yn * $yn);
	  if ($this->sn() < 0.0) $ra = -$ra;
	  $alat = pow((self::$re * $this->sf() / $ra), (1.0 / $this->sn()));
	  $alat = 2.0 * atan($alat) - M_PI * 0.5;

	  if (abs($xn) <= 0.0) {
		$theta = 0.0;
	  } else {
		if (abs($yn) <= 0.0) {
		  $theta = M_PI * 0.5;
		  if ($xn < 0.0) $theta = -$theta;
		} else
		  $theta = atan2($xn, $yn);
	  }
	  $alon = $theta / $this->sn() + self::$olon;
	  $rs['lat'] = $alat * self::$RADDEG;
	  $rs['lng'] = $alon * self::$RADDEG;

	  return $rs;
	}

  function gpsToGRID($v1, $v2) {
      $rs['lat'] = $v1;
      $rs['lng'] = $v2;
      $ra = tan(M_PI * 0.25 + ($v1) * self::$DEGRAD * 0.5);
      $ra = self::$re * $this->sf() / pow($ra, $this->sn());
      $theta = $v2 * self::$DEGRAD - self::$olon;
      if ($theta > M_PI) $theta -= 2.0 * M_PI;
      if ($theta < -M_PI) $theta += 2.0 * M_PI;
      $theta *= $this->sn();

      $rs['x'] = floor(($ra * sin($theta) + self::XO + 0.5));
      $rs['y'] = floor(($this->ro() - $ra * cos($theta) + self::YO + 0.5));

      return $rs;
  }
}
?>
