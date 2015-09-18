<?php

class BlueAcorn_UniversalAnalytics_Model_Js {

    /**
     * Generate an observer for the specified $event which calls an
     * anonymous function containing the provided $content
     *
     * @name observe
     * @param string $event
     * @param string $content
     * @return string
     */
    public function observe($event, $content) {
        $text = ".observe( '{$event}', ";
        $text .= $this->anonFunc('event', $content);
        $text .= ');';

        return $text;
    }

    /**
     * Generate a each loop that calls an anonymous function
     * containing $content
     *
     * @name each
     * @param string $content
     * @return string
     */
    public function each($content) {
        $text = '.each( ';
        $text .= $this->anonFunc('element, index, array', $content);
        $text .= ');';

        return $text;
    }

    /**
     * Generate an anonymous function using the provided $paramList
     * which runs $content
     *
     * @name anonFunc
     * @param string $paramList
     * @param string $content
     * @return string
     */
    public function anonFunc($paramList, $content) {
        $functionText  = 'function(' . $paramList . ') { ';
        $functionText .= $content;
        $functionText .= ' }';

        return $functionText;
    }

    /**
     * Generate a call to a JS function with any number of variables
     * as parameters.
     *
     * @name call
     * @param multi
     * @return string
     */
    public function call($name) {
        $params = func_get_args();
        $name = array_shift($params);
        $outputList = Array();

        foreach ($params as $element) {
            $outputList[] = Zend_Json::encode($element, false, array('enableJsonExprFinder' => true));
        }

        return "{$name}(" . implode(', ', $outputList) . ");\n";
    }

    /**
     * Generates a block of JS text for Universal Analytics
     * calls. This method takes an indeterminate number of parameters.
     *
     * @name generateGoogleJS
     * @param multi
     * @return string
     */
    public function generateGoogleJS() {
        $params = func_get_args();
        array_unshift($params, 'ga');
        
        return call_user_func_array(Array($this, 'call'), $params);
    }

    /**
     * Generate an observer for each of the found $target nodes on the
     * provided $action performing $observerCode
     *
     * @name attachForeachObserve
     * @param string $target
     * @param string $observedCode
     * @param string $action
     * @return string
     */
    public function attachForeachObserve($target, $observedCode, $action = 'click') {
        $text  = '$$(\'' . $target . '\')';
        $text .= $this->each('element' . $this->observe($action, $observedCode));
        $text .= "\n";

        return $text;
    }

}
