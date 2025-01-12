<?php

namespace Tintin;

use Tintin\Exception\DirectiveNotAllowException;

class Compiler
{
    use Lexique\CompileIf,
        Lexique\CompileLoop,
        Lexique\CompileEchos,
        Lexique\CompileRawPhp,
        Lexique\CompileComments,
        Lexique\CompileCustomDirective,
        Lexique\CompileExtends;

    /**
     * The echo tags
     *
     * @var array
     */
    protected $echo_tags = ['{{', '}}'];

    /**
     * The raw echo tags
     *
     * @var array
     */
    protected $raw_echo_tags = ['{{{', '}}}'];

    /**
     * @var array
     */
    protected $comments = ['{#', '#}'];

    /**
     * The valid token list
     *
     * @var array
     */
    protected $tokens = [
        'Comments',
        'RawPhp',
        'EchoStack',
        'IfStack',
        'LoopStack',
        'ExtendsStack',
        'CustomStack',
        'CustomDirective',
    ];

    /**
     * The compile result
     *
     * @var string
     */
    protected $result = '';

    /**
     * The expression pattern
     *
     * @var string
     */
    protected $condition_pattern = '/(%s\s*\((.+?)?\)$)+/sm';

    /**
     * The reverse inclusion using for #extends
     *
     * @var array
     */
    protected $extends_render = [];

    /**
     * The custom directive collector
     *
     * @var array
     */
    private $directives = [];

    /**
     * List of default directive
     *
     * @var array
     */
    private $directivesProtected = [
        'if',
        'else',
        'elseif',
        'elif',
        'endif',
        'unless',
        'extends',
        'block',
        'inject',
        'include',
        'endblock',
        'while',
        'endwhile',
        'for',
        'endfor',
        'loop',
        'endloop',
        'stop',
        'jump',
    ];

    /**
     * Launch the compilation
     *
     * @param array|string $data
     * @return string
     */
    public function compile($data)
    {
        $data = preg_split('/\n|\r\n/', $data);

        foreach ($data as $value) {
            if (strlen($value) > 0) {
                $value = $this->compileToken($value);

                $this->result .= strlen($value) == 0 || $value == ' ' ? trim($value) : $value."\n";
            }
        }

        return $this->resetCompilationAccumulator();
    }

    /**
     * Compile All define token
     *
     * @param string $value
     * @return string
     */
    private function compileToken($value)
    {
        foreach ($this->tokens as $token) {
            $out = $this->{'compile'.$token}($value);

            if ($token == 'Comments') {
                if (strlen($out) == 0) {
                    return "";
                }
            }

            if (strlen($out) !== 0) {
                $value = $out;
            }
        }

        return $value;
    }

    /**
     * Reset Compilation accumulator
     * @return string
     */
    private function resetCompilationAccumulator()
    {
        $result = $this->result.implode("\n", $this->extends_render);

        $this->result = '';

        $this->extends_render = [];

        return $result;
    }

    /**
     * Push more directive in template system
     *
     * @param string $name
     * @param callable $handler
     * @param boolean $broken
     * @return void
     * @throws DirectiveNotAllowException
     */
    public function pushDirective($name, $handler, $broken = false)
    {
        if (in_array($name, $this->directivesProtected)) {
            throw new DirectiveNotAllowException('The ' . $name . ' directive is not allow.');
        }

        $this->directives[$name] = compact('handler', 'broken');
    }

    /**
     * Execute custom directory
     *
     * @param string $name
     * @param array $params
     * @return mixed
     */
    public function _____executeCustomDirectory($name, ...$params)
    {
        if (!isset($this->directives[$name])) {
            return null;
        }

        $directive = $this->directives[$name];

        return call_user_func_array($directive['handler'], [$params]);
    }
}
