<?php
    // Retrieve User-Agent header from server variables with fallback
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // SECURITY FIX: Input validation before output encoding (defense-in-depth)
    // Validate length and reject excessively long User-Agent strings
    $max_length = 200;
    if (strlen($user_agent) > $max_length) {
        $user_agent = substr($user_agent, 0, $max_length);
    }
    
    // SECURITY FIX: Pattern validation to detect suspicious content
    // Reject User-Agent with excessive special characters or suspicious patterns
    $suspicious_pattern = '/[<>]{3,}|script|javascript:|data:|vbscript:|onload|onerror/i';
    if (preg_match($suspicious_pattern, $user_agent)) {
        // Replace with sanitized default for suspicious patterns
        $user_agent = 'Blocked - Suspicious User-Agent Detected';
    }
    
    // SECURITY FIX: Apply output encoding to prevent XSS attacks
    // Using htmlspecialchars with ENT_QUOTES to encode both single and double quotes
    // ENT_HTML5 ensures HTML5 compliance, UTF-8 prevents encoding-based bypasses
    // Note: If output context changes to HTML attribute or URL, encoding strategy must be updated
    $safe_user_agent = htmlspecialchars($user_agent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Output the safely encoded User-Agent string
    echo "<div>User-Agent: {$safe_user_agent}</div>";
?>
</body>
</html>

