<?php
// Find any SPL file
$splFiles = glob('C:\Windows\System32\spool\PRINTERS\*.SPL');
if (empty($splFiles)) {
    echo "No SPL files found in C:\Windows\System32\spool\PRINTERS\n";
    exit(1);
}
$f = $splFiles[0];
echo "Analyzing most recent SPL file: $f\n";

echo "File exists. Size: " . filesize($f) . " bytes\n";
$content = file_get_contents($f); // Read full file to find offset

// Hex dump start
echo "First 100 bytes hex:\n";
echo bin2hex(substr($content, 0, 100)) . "\n";

// Search for EMF signature
$emfSig = "\x01\x00\x00\x00";
$pos = strpos($content, $emfSig);

if ($pos !== false) {
    echo "EMF Signature found at offset: $pos\n";
    // Check if next chars are EMF (sometimes)
    // Actually standard EMF header starts with RecordType=1 (4 bytes), RecordSize (4 bytes), Bounds (16 bytes), Frame (16 bytes), Signature (4 bytes ' E M F')
    // Offset 40 (0x28) from start of EMR_HEADER usually contains 'FME ' (0x20454D46)
    
    $header = substr($content, $pos, 100);
    echo "Header at offset hex:\n" . bin2hex($header) . "\n";
    
    // Check for "EMF" magic at offset 40 relative to EMR_HEADER start
    // However, in SPL file, the EMF might be embedded.
    // Let's look for " E M F" (0x20454D46)
    $magicPos = strpos($content, "FME "); // Little endian ' EMF' is F M E space ? No ' E M F' is 0x46 0x4D 0x45 0x20
    // Real signature in file is 'FME ' if reading as string?
    // dSignature is 0x464D4520 (" EMF")
    
    // Let's just look for that sequence
    $emfMagic = "\x20\x45\x4D\x46"; 
    $magicPos = strpos($content, $emfMagic);
    if ($magicPos !== false) {
         echo "EMF Magic ' EMF' found at offset: $magicPos\n";
         // The start of EMF should be magicPos - 40
         $calcStart = $magicPos - 40;
         echo "Calculated EMF Start: $calcStart\n";
         
         if ($calcStart == $pos) {
             echo "Standard EMF header confirmed.\n";
         } else {
             echo "Mismatch with first 0x01000000 found.\n";
         }
    } else {
        echo "EMF Magic ' EMF' NOT found.\n";
    }

} else {
    echo "EMF RecordType=1 (0x01000000) NOT found.\n";
}
