<?php
/**
 * Created by PhpStorm.
 * User: Rhilip
 * Date: 2019/3/13
 * Time: 19:55
 */

namespace Rid\I18n;

use Rid\Base\Component;

class I18n extends Component
{

    /**
     * Language file path
     * This is the path for the language files.
     *
     * @var string
     */
    public $fileNamespace = '\apps\lang';

    /**
     * Allowed language
     * This is the set of language which is used to limit user languages. No-exist language will not accept.
     *
     * @var array
     */
    public $allowedLangSet = ['en', 'zh-CN'];

    /**
     * Fallback language
     * This is the language which is used when there is no language file for all other user languages. It has the lowest priority.
     * Remember to create a language file for the fallback!!
     *
     * @var string
     */
    public $fallbackLang = 'en';

    /**
     * Merge in fallback language
     * Whether to merge current language's strings with the strings of the fallback language ($fallbackLang).
     *
     * @var bool
     */
    public $mergeFallback = false;

    /**
     * Forced language
     * If you want to force a specific language define it here.
     *
     * @var string
     */
    public $forcedLang = null;

    /*
     * The following properties are only available after calling init().
     */
    protected $lastLang = null;

    public function onRequestBefore()
    {
        $lastLang = null;
    }

    /**
     * getUserLangs()
     * Returns the user languages
     * Normally it returns an array like this:
     * 1. Forced language
     * 2. Language in $_GET['lang']
     * 3. Language in $_SESSION['lang']
     * 4. HTTP_ACCEPT_LANGUAGE
     * 5. Fallback language
     * Note: duplicate values are deleted.
     *
     * @param null $reqLang
     * @return array with the user languages sorted by priority.
     */
    public function getUserLangs($reqLang = null) {
        $userLangs = array();

        // Quick Return the last used language list
        if ($this->lastLang != null && $reqLang == null) {
            return $this->lastLang;
        }

        // Highest priority: forced language
        if ($this->forcedLang != NULL) $userLangs[] = $this->forcedLang;

        // 1st highest priority: required language
        if ($reqLang != null) {
            $userLangs[] = $reqLang;
        } else {
            // 2nd highest priority: GET parameter 'lang'
            if (!is_null(app()->request->get('lang'))) $userLangs[] = app()->request->get('lang');

            // 3rd highest priority: SESSION parameter 'lang'
            if (!is_null(app()->user->getLang())) $userLangs[] = app()->user->getLang();

            // 4th highest priority: HTTP_ACCEPT_LANGUAGE
            if (!is_null(app()->request->header('accept_language'))) {
                /**
                 * We get headers like this string 'en-US,en;q=0.8,uk;q=0.6,ru;q=0.4' (length=32)
                 * And then sort to an array like this
                 *
                 * array(size=4)
                 *    'en-US'    => float 1
                 *    'en'       => float 0.8
                 *    'uk'       => float 0.6
                 *    'ru'       => float 0.4
                 *
                 */
                $prefLocales = array_reduce(
                    explode(',', app()->request->header('accept_language')),
                    function ($res, $el) {
                        list($l, $q) = array_merge(explode(';q=', $el), [1]);
                        $res[$l] = (float)$q;
                        return $res;
                    }, []);
                arsort($prefLocales);

                foreach ($prefLocales as $part => $q) {
                    $userLangs[] = $part;
                }
            }
        }

        // Lowest priority: fallback
        $userLangs[] = $this->fallbackLang;

        $userLangs = array_unique($userLangs);  // remove duplicate elements
        $this->lastLang = $userLangs;  // Store it for last use.
        return $userLangs;
    }

    protected function getConfigClassName($langcode) {
        $langcode = str_replace('-','_',$langcode);
        return $this->fileNamespace . '\\' . $langcode;
    }

    private function getLangList($lang = null) {
        $userLangs = $this->getUserLangs($lang);

        // remove illegal userLangs
        $userLangs2 = array();
        foreach ($userLangs as $key => $value) {
            // only allow a-z, A-Z and 0-9 and _ and -
            if (preg_match('/^[a-zA-Z0-9_-]*$/', $value) === 1 && in_array($value, $this->allowedLangSet)) {
                if (class_exists($this->getConfigClassName($value))) {
                    $userLangs2[] = $this->getConfigClassName($value);  // change it to class name
                } elseif (
                    // Fail back if main language exist
                    $value !== substr($value, 0, 2)
                    && class_exists($this->getConfigClassName(substr($value, 0, 2)))) {
                    $userLangs2[] = $this->getConfigClassName(substr($value, 0, 2));
                }
            }
        }

        // remove duplicate elements
        return array_unique($userLangs2);
    }


    public function trans($string, $args = null, $lang = null)
    {
        $langs = $this->getLangList($lang);

        $return = '';
        foreach ($langs as $item) {
            try {
                $return = constant($item . "::" . $string);
                break;
            } catch (\Exception $e) {
                app()->log->warning('A no-exist translation hit.',['lang_class' => $item,'string' => $string]);
            }
        }

        return $args ? vsprintf($return, $args) : $return;
    }
}
