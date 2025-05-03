<?php
// chatbot-format.php

function formatted_phone($number) {
  $digits = preg_replace('/\D/', '', $number);
  return preg_match('/^(\d{3})(\d{3})(\d{4})$/', $digits, $m)
    ? "({$m[1]}) {$m[2]}-{$m[3]}"
    : $number;
}

function format_business_cards($matches, $maxVisible = 3) {
    $reply = "Here are some businesses that might help:\n\n";

    foreach ($matches as $i => $match) {
        $m = $match['metadata'] ?? [];
        $company = esc_html($m['company'] ?? 'Business');
        $website = esc_url($m['website'] ?? '');
        $phone = preg_replace('/[^0-9]/', '', $m['phone'] ?? '');
        $formatted_phone = formatted_phone($m['phone'] ?? '');
        $summary = esc_html($m['summary'] ?? '');
        $web_post_url = esc_url($m['web_post_url'] ?? '');
        $video_url = esc_url($m['video_url'] ?? '');
        $extraClass = ($i >= $maxVisible) ? ' chatbot-card-hidden' : '';

        $reply .= "<div class=\"chatbot-card{$extraClass}\">";
        $reply .= "<strong>{$company}</strong><br>";
        if ($website) $reply .= "🌐 <a href=\"{$website}\" target=\"_blank\">{$website}</a><br>";
        if ($phone) $reply .= "📞 <a href=\"tel:{$phone}\">{$formatted_phone}</a><br>";
        if ($video_url) {
            $reply .= "🎥 <a href=\"{$video_url}\" class=\"chatbot-video-link\"" .
                     " data-company=\"{$company}\">Watch Video</a><br>";
        }
        if ($summary) $reply .= "<p>{$summary}</p>";
        $reply .= "</div>\n";
    }

    if (count($matches) > $maxVisible) {
        $reply .= '<div class="chatbot-show-more-wrapper" style="text-align:center;margin-top:10px;">
            <button class="chatbot-show-more" onclick="document.querySelectorAll(\'.chatbot-card-hidden\').forEach(el => el.style.display = \'block\'); this.remove();">Show more results</button>
        </div>';
    }

    return $reply;
}

// ✅ Added wrapper for use in AJAX handler
function format_pinecone_matches($matches) {
  return format_business_cards($matches, 3); // Can adjust default visibility here
}