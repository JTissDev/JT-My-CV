<?php
/**
 * Utility function to send PHP variables to the browser JavaScript console.
 * Works seamlessly inside iframes and views.
 * 
 * @author    J.Tiss <jtissdev@gmail.com>
 * @since     1.0.0
 * @version   1.0.0
 * 
 * @param     mixed  $data  Any PHP variable (object, array, string, etc.) to inspect.
 * @return    void
 */
function debugConsole(mixed $data): void {
    // Converts the PHP object or array into a valid JSON string for JS
    $json = json_encode($data);
    
    // Outputs a script tag that executes console.log in the browser
    echo "<script>console.log(" . $json . ");</script>";
}