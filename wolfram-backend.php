<?php

function xml2array($xmlObject, $out = []) {
	foreach (((array)$xmlObject) as $index => $node) {
		$out[$index] = (is_object($node)) ? xml2array($node) : $node;
	}

	return $out;
}

if (!isset($_GET["query"]) || empty($_GET["query"])) {
	die(json_encode(["success" => false]));
}
if (!isset($_GET["states"]) || empty($_GET["states"])) {
	die(json_encode(["success" => false]));
}

$queryStr  = "http://api.wolframalpha.com/v2/query?input=";
$queryStr .= urlencode($_GET["query"]);
$queryStr .= "&appid=";
$queryStr .= getenv("WOLFRAM_TOKEN");
foreach ($_GET["states"] as $state) {
	$queryStr .= "&podstate=";
	$queryStr .= urlencode($state);
}

$results = file_get_contents($queryStr);
$results = simplexml_load_string($results);
$results = json_decode(json_encode($results), true);

if ($results["@attributes"]["error"] == "true" || $results["@attributes"]["success"] == "false") {
	die(json_encode(["success" => false]));
}

$resultArr = ["success" => true, "items" => []];

// warnings

if (isset($results["warnings"])) {
	$warningItems = [];
	foreach ($results["warnings"] as $key => $warning) {
		if ($key == "@attributes") {
			continue;
		}
		if (isset($warning["@attributes"])) {
			$warningItems[] = htmlspecialchars($warning["@attributes"]["text"]);
		} else {
			foreach ($warning as $subWarning) {
				$warningItems[] = htmlspecialchars($subWarning["@attributes"]["text"]);
			}
		}
	}
	$resultArr["items"][] = ["type" => "warnings", "value" => array_values($warningItems)];
}

// pods

$pods = [];

$podItems = [];

foreach ($results["pod"] as $pod) {
	$pods[$pod["@attributes"]["title"]] = $pod;
}

uasort($pods, function($a, $b) {
	return $a["@attributes"]["position"] - $b["@attributes"]["position"];
});

foreach ($pods as $pod) {
	$podItem = ["type" => "pod", "subpods" => [], "title" => htmlspecialchars($pod["@attributes"]["title"]), "states" => [], "info" => []];

	if (isset($pod["subpod"]["@attributes"])) {
		$subpodItem = [
			"type" => "subpod", 
			"title" => htmlspecialchars($pod["subpod"]["@attributes"]["title"]), 
			"img" => "<img src=\"".$pod["subpod"]["img"]["@attributes"]["src"]."\" width=\"".$pod["subpod"]["img"]["@attributes"]["width"]."\" height=\"".$pod["subpod"]["img"]["@attributes"]["height"]."\"/>", 
			"text" => (isset($pod["subpod"]["plaintext"]) ? $pod["subpod"]["plaintext"] : ""), 
			"states" => []
		];

		if (isset($pod["subpod"]["states"])) {
			$states = [];
			if (isset($pod["subpod"]["states"]["statelist"])) {
				if (isset($pod["subpod"]["states"]["statelist"]["@attributes"])) {
					$states = array_merge($states, $pod["subpod"]["states"]["statelist"]["state"]);
				} else {
					foreach ($pod["subpod"]["states"]["statelist"] as $statelist) {
						$states = array_merge($states, $statelist["state"]);
					}
				}
			}
			if (isset($pod["subpod"]["states"]["state"]["@attributes"])) {
				$states[] = $pod["subpod"]["states"]["state"];
			} else {
				$states = array_merge($states, $pod["subpod"]["states"]["state"]);
			}
			foreach ($states as $state) {
				$subpodItem["states"][] = ["type" => "state", "name" => htmlspecialchars($state["@attributes"]["name"]), "value" => htmlspecialchars($state["@attributes"]["input"])];
			}
		}

		$podItem["subpods"][] = $subpodItem;
	} else {
		foreach ($pod["subpod"] as $subpod) {
			$subpodItem = [
				"type" => "subpod", 
				"title" => htmlspecialchars($subpod["@attributes"]["title"]), 
				"img" => "<img src=\"".$subpod["img"]["@attributes"]["src"]."\" width=\"".$subpod["img"]["@attributes"]["width"]."\" height=\"".$subpod["img"]["@attributes"]["height"]."\"/>", 
				"text" => (isset($subpod["plaintext"]) ? $subpod["plaintext"] : ""), 
				"states" => []
			];

			if (isset($subpod["states"])) {
				$states = [];
				if (isset($subpod["states"]["statelist"])) {
					if (isset($subpod["states"]["statelist"]["@attributes"])) {
						$states = array_merge($states, $subpod["states"]["statelist"]["state"]);
					} else {
						foreach ($subpod["states"]["statelist"] as $statelist) {
							$states = array_merge($states, $statelist["state"]);
						}
					}
				}
				if (isset($subpod["states"]["state"]["@attributes"])) {
					$states[] = $subpod["states"]["state"];
				} else {
					$states = array_merge($states, $subpod["states"]["state"]);
				}
				foreach ($states as $state) {
					$subpodItem["states"][] = ["type" => "state", "name" => htmlspecialchars($state["@attributes"]["name"]), "value" => htmlspecialchars($state["@attributes"]["input"])];
				}
			}

			$podItem["subpods"][] = $subpodItem;
		}
	}

	if (isset($pod["states"])) {
		$states = [];
		if (isset($pod["states"]["statelist"])) {
			if (isset($pod["states"]["statelist"]["@attributes"])) {
				$states = array_merge($states, $pod["states"]["statelist"]["state"]);
			} else {
				foreach ($pod["states"]["statelist"] as $statelist) {
					$states = array_merge($states, $statelist["state"]);
				}
			}
		}
		if (isset($pod["states"]["state"]["@attributes"])) {
			$states[] = $pod["states"]["state"];
		} else {
			$states = array_merge($states, $pod["states"]["state"]);
		}
		foreach ($states as $state) {
			$podItem["states"][] = ["type" => "state", "name" => htmlspecialchars($state["@attributes"]["name"]), "value" => htmlspecialchars($state["@attributes"]["input"])];
		}
	}

	if (isset($pod["infos"])) {
		foreach ($pod["infos"]["info"] as $key => $info) {
			if ($key === "@attributes") {
				continue;
			}

			$infoItem = ["type" => "info", "value" => []];
			
			if (isset($info["@attributes"]) && isset($info["@attributes"]["text"])) {
				$infoItem["value"][] = ["type" => "text", "value" => htmlspecialchars($info["@attributes"]["text"])];
			}

			if (isset($info["img"])) {
				if (isset($info["img"]["@attributes"])) {
					$infoItem["value"][] = ["type" => "img", "value" => "<img src=\"".$info["img"]["@attributes"]["src"]."\" width=\"".$info["img"]["@attributes"]["width"]."\" height=\"".$info["img"]["@attributes"]["height"]."\"/>"];					
				} else {
					foreach ($info["img"] as $img) {
						$infoItem["value"][] = ["type" => "img", "value" => "<img src=\"".$img["@attributes"]["src"]."\" width=\"".$img["@attributes"]["width"]."\" height=\"".$img["@attributes"]["height"]."\"/>"];					
					}
				}
			}

			if (isset($info["link"])) {
				if (isset($info["link"]["@attributes"])) {
					$infoItem["value"][] = ["type" => "link", "href" => $info["link"]["@attributes"]["url"], "text" => htmlspecialchars($info["link"]["@attributes"]["text"])];
				} else {
					foreach ($info["link"] as $link) {
						$infoItem["value"][] = ["type" => "link", "href" => $link["@attributes"]["url"], "text" => htmlspecialchars($link["@attributes"]["text"])];
					}
				}
			}

			if (isset($info["units"])) {
				if (isset($info["units"]["unit"]["@attributes"])) {
					$infoItem["value"][] = ["type" => "text", "value" => htmlspecialchars($info["units"]["unit"]["@attributes"]["short"]." -> ".$info["units"]["unit"]["@attributes"]["long"])];
				} else {
					foreach ($info["units"]["unit"] as $unit) {
						$infoItem["value"][] = ["type" => "text", "value" => htmlspecialchars($unit["@attributes"]["short"]." -> ".$unit["@attributes"]["long"])];
					}
				}
				$infoItem["value"][] = ["type" => "img", "value" => "<img src=\"".$info["units"]["img"]["@attributes"]["src"]."\" width=\"".$info["units"]["img"]["@attributes"]["width"]."\" height=\"".$info["units"]["img"]["@attributes"]["height"]."\"/>"];
			}

			$podItem["info"][] = $infoItem;
		}
	}

	$podItems[] = $podItem;
}

$resultArr["items"][] = ["type" => "pods", "value" => $podItems];

// sources

die(json_encode($resultArr, JSON_PRETTY_PRINT));
