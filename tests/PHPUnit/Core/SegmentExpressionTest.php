<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
class SegmentExpressionTest extends PHPUnit_Framework_TestCase
{
    /**
     * Dataprovider for testSegmentSqlSimpleNoOperation
     * @return array
     */
    public function getSimpleSegmentExpressions()
    {
        return array(
            // classic expressions
            array('A', " A "),
            array('A,B', " (A OR B )"),
            array('A;B', " A AND B "),
            array('A;B;C', " A AND B AND C "),
            array('A,B;C,D;E,F,G', " (A OR B) AND (C OR D) AND (E OR F OR G )"),

            // unescape the backslash
            array('A\,B\,C,D', " (A,B,C OR D )"),
            array('\,A', ' ,A '),
            // unescape only when it was escaping a known delimiter
            array('\\\A', ' \\\A '),
            // unescape at the end
            array('\,\;\A\B,\,C,D\;E\,', ' (,;\A\B OR ,C OR D;E, )'),

            // only replace when a following expression is detected
            array('A,', ' A, '),
            array('A;', ' A; '),
            array('A;B;', ' A AND B; '),
            array('A,B,', ' (A OR B, )'),
        );
    }

    /**
     * @dataProvider getSimpleSegmentExpressions
     * @group Core
     * @group SegmentExpression
     */
    public function testSegmentSqlSimpleNoOperation($expression, $expectedSql)
    {
        $segment = new Piwik_SegmentExpression($expression);
        $expected = array('where' => $expectedSql, 'bind' => array(), 'join' => '');
        $processed = $segment->getSql();
        $this->assertEquals($expected, $processed);
    }

    /**
     * Dataprovider for testSegmentSqlWithOperations
     * @return array
     */
    public function getOperationSegmentExpressions()
    {
        // Filter expression => SQL string + Bind values
        return array(
            array('A==B%', array('where' => " A = ? ", 'bind' => array('B%'))),
            array('ABCDEF====B===', array('where' => " ABCDEF = ? ", 'bind' => array('==B==='))),
            array('A===B;CDEF!=C!=', array('where' => " A = ? AND CDEF <> ? ", 'bind' => array('=B', 'C!=' ))),
            array('A==B,C==D', array('where' => " (A = ? OR C = ? )", 'bind' => array('B', 'D'))),
            array('A!=B;C==D', array('where' => " A <> ? AND C = ? ", 'bind' => array('B', 'D'))),
            array('A!=B;C==D,E!=Hello World!=', array('where' => " A <> ? AND (C = ? OR E <> ? )", 'bind' => array('B', 'D', 'Hello World!='))),

            array('A>B', array('where' => " A > ? ", 'bind' => array('B'))),
            array('A<B', array('where' => " A < ? ", 'bind' => array('B'))),
            array('A<=B', array('where' => " A <= ? ", 'bind' => array('B'))),
            array('A>=B', array('where' => " A >= ? ", 'bind' => array('B'))),
            array('ABCDEF>=>=>=B===', array('where' => " ABCDEF >= ? ", 'bind' => array('>=>=B==='))),
            array('A>=<=B;CDEF>G;H>=I;J<K;L<=M', array('where' => " A >= ? AND CDEF > ? AND H >= ? AND J < ? AND L <= ? ", 'bind' => array('<=B', 'G','I','K','M' ))),
            array('A>=B;C>=D,E<w_ow great!', array('where' => " A >= ? AND (C >= ? OR E < ? )", 'bind' => array('B', 'D', 'w_ow great!'))),

            array('A=@B_', array('where' => " A LIKE ? ", 'bind' => array('%B\_%'))),
            array('A!@B%', array('where' => " A NOT LIKE ? ", 'bind' => array('%B\%%'))),
        );
    }

    /**
     * @dataProvider getOperationSegmentExpressions
     * @group Core
     * @group SegmentExpression
     */
    public function testSegmentSqlWithOperations($expression, $expectedSql)
    {
        $segment = new Piwik_SegmentExpression($expression);
        $segment->parseSubExpressions();
        $segment->parseSubExpressionsIntoSqlExpressions();
        $processed = $segment->getSql();
        $expectedSql['join'] = '';
        $this->assertEquals($expectedSql, $processed);
    }

    /**
     * Dataprovider for testBogusFiltersExpectExceptionThrown
     * @return array
     */
    public function getBogusFilters()
    {
        return array(
            array('A=B'),
            array('C!D'),
            array(''),
            array('      '),
            array(',;,'),
            array(','),
            array(',,'),
            array('==='),
            array('!=')
        );
    }

    /**
     * @dataProvider getBogusFilters
     * @group Core
     * @group SegmentExpression
     */
    public function testBogusFiltersExpectExceptionThrown($bogus)
    {
        try {
            $segment = new Piwik_SegmentExpression($bogus);
            $segment->parseSubExpressions();
            $segment->getSql();
        } catch (Exception $e) {
            return;
        }
        $this->fail('Expected exception not raised');
    }
}
