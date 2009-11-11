<?php

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Base loader class for all builtin loaders.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
abstract class Twig_Loader implements Twig_LoaderInterface
{
  protected $cache;
  protected $autoReload;
  protected $env;

  /**
   * Constructor.
   *
   * The cache can be one of three values:
   *
   *  * null (the default): Twig will create a sub-directory under the system tmp directory
   *         (not recommended as templates from two projects with the same name will share the cache)
   *
   *  * false: disable the compile cache altogether
   *
   *  * An absolute path where to store the compiled templates
   *
   * @param string  $cache      The compiler cache directory
   * @param Boolean $autoReload Whether to reload the template is the original source changed
   */
  public function __construct($cache = null, $autoReload = true)
  {
    $this->cache = null === $cache ? sys_get_temp_dir().DIRECTORY_SEPARATOR.'twig_'.md5(dirname(__FILE__)) : $cache;

    if (false !== $this->cache && !is_dir($this->cache))
    {
      mkdir($this->cache, 0755, true);
    }

    $this->autoReload = $autoReload;
  }

  /**
   * Loads a template by name.
   *
   * @param  string $name The template name
   *
   * @return string The class name of the compiled template
   */
  public function load($name)
  {
    $cls = $this->getTemplateName($name);

    if (class_exists($cls, false))
    {
      return $cls;
    }

    if (false === $this->cache)
    {
      list($source, ) = $this->getSource($name);
      $this->evalString($source, $name);

      return $cls;
    }

    $cache = $this->getCacheFilename($name);
    if (!file_exists($cache))
    {
      list($source, $mtime) = $this->getSource($name);
      if (false === $mtime)
      {
        $this->evalString($source, $name);

        return $cls;
      }

      $this->save($this->compile($source, $name), $cache);
    }
    elseif ($this->autoReload)
    {
      list($source, $mtime) = $this->getSource($name);
      if (filemtime($cache) < $mtime)
      {
        $this->save($this->compile($source, $name), $cache);
      }
    }

    require_once $cache;

    return $cls;
  }

  /**
   * Saves a PHP string in the cache.
   *
   * If the cache file cannot be written, then the PHP string is evaluated.
   *
   * @param string $content The PHP string
   * @param string $cache   The absolute path of the cache
   */
  protected function save($content, $cache)
  {
    if ($fp = @fopen($cache, 'w'))
    {
      fclose($fp);
      file_put_contents($cache, $content);
    }
    else
    {
      eval('?>'.$content);
    }
  }

  /**
   * Sets the Environment related to this loader.
   *
   * @param Twig_Environment $env A Twig_Environment instance
   */
  public function setEnvironment(Twig_Environment $env)
  {
    $this->env = $env;
  }

  public function getTemplateName($name)
  {
    return '__TwigTemplate_'.md5($name);
  }

  public function getCacheFilename($name)
  {
    return $this->cache.'/twig_'.md5($name).'.php';
  }

  protected function compile($source, $name)
  {
    return $this->env->compile($this->env->parse($this->env->tokenize($source, $name)));
  }

  protected function evalString($source, $name)
  {
    eval('?>'.$this->compile($source, $name));
  }

  /**
   * Gets the source code of a template, given its name.
   *
   * @param  string $name string The name of the template to load
   *
   * @return array An array consisting of the source code as the first element,
   *               and the last modification time as the second one
   *               or false if it's not relevant
   */
  abstract protected function getSource($name);
}
