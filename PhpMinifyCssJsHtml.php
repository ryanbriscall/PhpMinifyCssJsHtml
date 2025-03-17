function minifyCSS($css) {
	preg_match_all('/(\"[^\"]*\"|\'[^\']*\'|url\([^)]+\))/', $css, $matches);
	$placeholders = [];
	
	foreach ($matches[0] as $i => $match) {
		$placeholder = "__CSS_STRING_".$i."__";
		$css = str_replace($match, $placeholder, $css);
		$placeholders[$placeholder] = $match;
	}

	$css = preg_replace('/\s*{\s*/', '{', $css);
	$css = preg_replace('/\s*}\s*/', '}', $css);
	$css = preg_replace('/\s*:\s*/', ':', $css);
	$css = preg_replace('/\s*;\s*/', ';', $css);
	$css = preg_replace('/;}/', '}', $css);
	$css = preg_replace('/\s+/', ' ', $css);
	$css = trim($css);

	foreach ($placeholders as $placeholder => $original) {
		$css = str_replace($placeholder, $original, $css);
	}

	return str_replace("\n", '', $css);
}

function minifyJS($js) {
	preg_match_all('/("([^"\\\\]|\\\\.)*"|\'([^\'\\\\]|\\\\.)*\'|`([^`\\\\]|\\\\.)*`)/', $js, $matches);
	$placeholders = [];

	foreach ($matches[0] as $i => $match) {
		$placeholder = "__JS_STRING_" . $i . "__";
		$js = str_replace($match, $placeholder, $js);
		$placeholders[$placeholder] = $match;
	}

	$js = preg_replace('/\/\*.*?\*\//s', '', $js);
	$js = preg_replace('/\/\/[^\n]*/', '', $js);
	$js = preg_replace('/\s*([{}();,:+<>=|&!])\s*/', '$1', $js);
	$js = preg_replace('/;\}/', '}', $js);
	$js = preg_replace('/\s+/', ' ', $js);
	$js = trim($js);

	foreach ($placeholders as $placeholder => $original) {
		if ($original[0] === '`') {
			$content = substr($original, 1, -1);
			$content = preg_replace('/\s+/', ' ', $content);
			$content = preg_replace('/\s*([<>])\s*/', '$1', $content);
			$content = trim($content);
			$original = "`" . $content . "`";
		}
		$js = str_replace($placeholder, $original, $js);
	}

	return str_replace("\n", '', $js);
}

function minifyHTML($html) {
	$placeholders = [];

	$html = preg_replace_callback(
		'/<(script|style)(\b[^>]*)>.*?<\/\1>/is',
		function ($matches) use (&$placeholders) {
			if (strpos($matches[2], 'src=') !== false || strpos($matches[2], 'href=') !== false) {
				return $matches[0];
			}

			$key = "__PLACEHOLDER_" . count($placeholders) . "__";
			$placeholders[$key] = $matches[0];
			return $key;
		},
		$html
	);

	$html = preg_replace_callback(
		'/<(pre|textarea|code)\b[^>]*>.*?<\/\1>/is',
		function ($matches) use (&$placeholders) {
			$key = "__PLACEHOLDER_" . count($placeholders) . "__";
			$placeholders[$key] = $matches[0];
			return $key;
		},
		$html
	);

	$html = preg_replace('/\s*>\s+/', '> ', $html);
	$html = preg_replace('/\s+</', ' <', $html);

	$html = preg_replace_callback(
		'/>(\s+)</',
		function ($matches) {
			return '> ' . trim($matches[1]) . ' <';
		},
		$html
	);

	$html = preg_replace('/\s+/', ' ', $html);
	$html = preg_replace('/<!--(?!\[if).*?-->/', '', $html);

	foreach ($placeholders as $key => $original) {
		if (preg_match('/^<(script|style)/i', $original, $tag)) {
			if (stripos($tag[1], 'script') !== false) {
				$original = preg_replace_callback(
					'/<script(\b[^>]*)>(.*?)<\/script>/is',
					function ($matches) {
						return "<script" . $matches[1] . ">" . minifyJS($matches[2]) . "</script>";
					},
					$original
				);
			}
			elseif (stripos($tag[1], 'style') !== false) {
				$original = preg_replace_callback(
					'/<style(\b[^>]*)>(.*?)<\/style>/is',
					function ($matches) {
						return "<style" . $matches[1] . ">" . minifyCSS($matches[2]) . "</style>";
					},
					$original
				);
			}
		}
		$html = str_replace($key, $original, $html);
	}

	return trim($html);
}
