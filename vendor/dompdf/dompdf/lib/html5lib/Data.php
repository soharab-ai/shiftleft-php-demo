<?php

// warning: this file is encoded in UTF-8!

class HTML5_Data
{

    // at some point this should be moved to a .ser file. Another
    // possible optimization is to give UTF-8 bytes, not Unicode
    // codepoints
    // XXX: Not quite sure why it's named this; this is
    // actually the numeric entity dereference table.
    protected static $realCodepointTable = array(
        0x00 => 0xFFFD, // REPLACEMENT CHARACTER
        0x0D => 0x000A, // LINE FEED (LF)
        0x80 => 0x20AC, // EURO SIGN ('€')
        0x81 => 0x0081, // <control>
        0x82 => 0x201A, // SINGLE LOW-9 QUOTATION MARK ('‚')
        0x83 => 0x0192, // LATIN SMALL LETTER F WITH HOOK ('ƒ')
        0x84 => 0x201E, // DOUBLE LOW-9 QUOTATION MARK ('„')
        0x85 => 0x2026, // HORIZONTAL ELLIPSIS ('…')
        0x86 => 0x2020, // DAGGER ('†')
        0x87 => 0x2021, // DOUBLE DAGGER ('‡')
        0x88 => 0x02C6, // MODIFIER LETTER CIRCUMFLEX ACCENT ('ˆ')
        0x89 => 0x2030, // PER MILLE SIGN ('‰')
        0x8A => 0x0160, // LATIN CAPITAL LETTER S WITH CARON ('Š')
        0x8B => 0x2039, // SINGLE LEFT-POINTING ANGLE QUOTATION MARK ('‹')
        0x8C => 0x0152, // LATIN CAPITAL LIGATURE OE ('Œ')
        0x8D => 0x008D, // <control>
        0x8E => 0x017D, // LATIN CAPITAL LETTER Z WITH CARON ('Ž')
        0x8F => 0x008F, // <control>
        0x90 => 0x0090, // <control>
        0x91 => 0x2018, // LEFT SINGLE QUOTATION MARK ('‘')
        0x92 => 0x2019, // RIGHT SINGLE QUOTATION MARK ('’')
        0x93 => 0x201C, // LEFT DOUBLE QUOTATION MARK ('“')
        0x94 => 0x201D, // RIGHT DOUBLE QUOTATION MARK ('”')
        0x95 => 0x2022, // BULLET ('•')
        0x96 => 0x2013, // EN DASH ('–')
        0x97 => 0x2014, // EM DASH ('—')
        0x98 => 0x02DC, // SMALL TILDE ('˜')
        0x99 => 0x2122, // TRADE MARK SIGN ('™')
        0x9A => 0x0161, // LATIN SMALL LETTER S WITH CARON ('š')
        0x9B => 0x203A, // SINGLE RIGHT-POINTING ANGLE QUOTATION MARK ('›')
        0x9C => 0x0153, // LATIN SMALL LIGATURE OE ('œ')
        0x9D => 0x009D, // <control>
        0x9E => 0x017E, // LATIN SMALL LETTER Z WITH CARON ('ž')
        0x9F => 0x0178, // LATIN CAPITAL LETTER Y WITH DIAERESIS ('Ÿ')
    );

    protected static $namedCharacterReferences;

    protected static $namedCharacterReferenceMaxLength;

    /**
     * Returns the "real" Unicode codepoint of a malformed character
     * reference.
     */
    public static function getRealCodepoint($ref) {
        if (!isset(self::$realCodepointTable[$ref])) return false;
        else return self::$realCodepointTable[$ref];
    }

public static function getNamedCharacterReferences() {
    if (!self::$namedCharacterReferences) {
        // MITIGATION: Define maximum allowed file size to prevent DoS attacks (5MB)
        $maxFileSize = 5 * 1024 * 1024;
        
        $jsonFilePath = dirname(__FILE__) . '/named-character-references.json';
        $serFilePath = dirname(__FILE__) . '/named-character-references.ser';
        
        // MITIGATION: One-time migration from .ser to .json if needed
        if (!file_exists($jsonFilePath) && file_exists($serFilePath)) {
            // Perform one-time migration with controlled conditions
            if (filesize($serFilePath) > $maxFileSize) {
                throw new RuntimeException('Serialized file exceeds maximum allowed size during migration');
            }
            
            $serializedData = file_get_contents($serFilePath);
            // MITIGATION: Use allowed_classes => false during migration only
            $migratedData = unserialize($serializedData, ['allowed_classes' => false]);
            
            if ($migratedData === false) {
                throw new RuntimeException('Failed to unserialize data during migration');
            }
            
            // Convert to JSON format
            $jsonData = json_encode($migratedData);
            if ($jsonData === false) {
                throw new RuntimeException('Failed to encode data to JSON during migration');
            }
            
            // Write JSON file
            if (file_put_contents($jsonFilePath, $jsonData) === false) {
                throw new RuntimeException('Failed to write JSON file during migration');
            }
            
            // Rename .ser file to prevent future use
            @rename($serFilePath, $serFilePath . '.migrated');
            
            // Log migration event for audit purposes
            error_log('Named character references migrated from .ser to .json format');
        }
        
        // MITIGATION: Verify JSON file exists after migration
        if (!file_exists($jsonFilePath)) {
            throw new RuntimeException('Named character references JSON file not found');
        }
        
        // MITIGATION: Check file size before loading to prevent memory exhaustion
        if (filesize($jsonFilePath) > $maxFileSize) {
            throw new RuntimeException('Character references file exceeds maximum allowed size');
        }
        
        // MITIGATION: Load JSON file content
        $jsonContent = file_get_contents($jsonFilePath);
        if ($jsonContent === false) {
            throw new RuntimeException('Failed to read character references file');
        }
        
        // MITIGATION: Use json_decode as safe alternative to unserialize
        self::$namedCharacterReferences = json_decode($jsonContent, true);
        
        // MITIGATION: Validate JSON decoding result
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode character references: ' . json_last_error_msg());
        }
        
        // MITIGATION: Strict data type validation - ensure result is an array
        if (!is_array(self::$namedCharacterReferences)) {
            throw new RuntimeException('Invalid character references format: expected array');
private static function validateCharacterReferences($data) {
    // MITIGATION: Schema validation to prevent JSON injection attacks
    foreach ($data as $key => $value) {
        // Validate key is a string
        if (!is_string($key)) {
            throw new RuntimeException('Invalid character reference: key must be a string');
        }
        
        // Validate key matches expected character reference pattern (alphanumeric with optional semicolon)
        if (!preg_match('/^[a-zA-Z0-9]+;?$/', $key)) {
            throw new RuntimeException('Invalid character reference key format: ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8'));
        }
        
        // Validate value is an array
        if (!is_array($value)) {
            throw new RuntimeException('Invalid character reference value: expected array for key ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8'));
        }
        
        // Validate value contains exactly two integer elements (character codes)
        if (count($value) !== 2 || !is_int($value[0]) || !is_int($value[1])) {
            throw new RuntimeException('Invalid character reference value structure for key ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8'));
        }
    }
}
