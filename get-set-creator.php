<?php
/**
 * PHP Get Set Creator
 *
 * This is a simple script to create property getter/setter methods for a PHP
 * class by way of property reflection.
 *
 * @author David Frame <deframe@cryst.co.uk>
 * @version 0.1
 *
 * @todo Refactor into a class!
 */

// Change the source dir constant to suit your project!

define('SOURCE_DIR', '/web/my-project/source');

if (!array_key_exists('class', $_GET) || empty($_GET['class'])) {
  die('Class not specified');
}

$sClass     = $_GET['class'];
$sClassFile = SOURCE_DIR . '/' . str_replace('_', '/', $_GET['class']) . '.php';

if (!file_exists($sClassFile)) {
  die($sClassFile . ' does not exist!');
}

// Use reflection to grab class / property details.

$oClass      = new ReflectionClass($sClass);
$aProperties = $oClass->getProperties();

// Quick check of the number of get() and set() methods in the class.

if (!array_key_exists('override', $_GET) || $_GET['override'] != '1') {

  $aMethods = $oClass->getMethods();

  $nNumberOfGetMethods = 0;
  $nNumberOfSetMethods = 0;

  foreach ($aMethods as $oMethod) {
    if (substr($oMethod->getName(), 0, 3) == 'get') {
      $nNumberOfGetMethods ++;
    } else if (substr($oMethod->getName(), 0, 3) == 'set') {
      $nNumberOfSetMethods ++;
    }
  }

  if ($nNumberOfGetMethods > 0 || $nNumberOfSetMethods > 0) {
    die(
      'The provided class already contains ' . $nNumberOfGetMethods . ' get*() '
      . 'methods and ' . $nNumberOfSetMethods . ' set*() methods. If you are '
      . 'absolutely sure you want to operate on this class, please add '
      . '"&amp;override=1" to the query string'
    );
  }

}

$sCodeToAdd = '';

foreach ($aProperties as $oProperty) {

  // Derive property name, description and type.

  $sPropertyName = $oProperty->getName();

  $sPropertyDescription = '';
  $sPropertyType        = '';

  $sDocComment = $oProperty->getDocComment();
  $aDocComment = explode("\n", $sDocComment);

  array_walk($aDocComment, create_function('&$val', '$val = trim($val);'));

  if (preg_match('#^\*\s+(.+)$#', $aDocComment[1], $aMatches)) {
    $sPropertyDescription = $aMatches[1];
    $sPropertyDescription = preg_replace('#(.+)\.$#', '\\1', $sPropertyDescription);
  }

  foreach ($aDocComment as $sDocCommentLine) {
    if (preg_match('#^\*\s+@var\s+(.+)$#', $sDocCommentLine, $aMatches)) {
      $sPropertyType = $aMatches[1];
    }
  }

  $sStudlyPropertyName = preg_replace('#^([a-z])(.+)$#', '\\2', $sPropertyName);

  // Create the set/get code for the property.

  $sCodeToAdd .= '
  /**
   * Set ' . $sPropertyDescription . '.
   *
   * @param ' . $sPropertyType . ' $' . $sPropertyName . ' ' . $sPropertyDescription . '
   * @return ' . $oClass->getName() . ' Fluent interface
   */
  public function set' . $sStudlyPropertyName . '($' . $sPropertyName . ')
  {
    $this->' . $sPropertyName . ' = $' . $sPropertyName . ';
    return $this;
  }

  /**
   * Get ' . $sPropertyDescription . '.
   *
   * @return ' . $sPropertyType . ' ' . $sPropertyDescription . '
   */
  public function get' . $sStudlyPropertyName . '()
  {
    return $this->' . $sPropertyName . ';
  }
';

}

if (!empty($sCodeToAdd)) {

  $sClassFileContents = file_get_contents($sClassFile);

  // Remove the last closing brace (which closes the class definition).

  $sClassFileContents = preg_replace('#\s*}\s*$#', '', $sClassFileContents);

  // Merge in the getter/setter code.

  $sClassFileContents .= "\n" . $sCodeToAdd;

  // Re-add the last closing brace.

  $sClassFileContents .= "\n" . '}';

  // Make a backup of the class and save the new version.

  copy($sClassFile, $sClassFile . '.bak');
  unlink($sClassFile);
  file_put_contents($sClassFile, $sClassFileContents);

  echo $sClassFile . ' saved with new getter/setter methods';

}