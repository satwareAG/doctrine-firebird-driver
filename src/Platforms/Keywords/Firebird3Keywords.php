<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Kafoso\DoctrineFirebirdDriver\Platforms\Keywords;

class Firebird3Keywords extends FirebirdInterbaseKeywords
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Firebird3';
    }

    /**
     * {@inheritdoc}
     * @link https://firebirdsql.org/refdocs/langrefupd25-reskeywords-full-reswords.html
     */
    protected function getKeywords()
    {
        return array_merge(parent::getKeywords(), [
            'BOOLEAN',
            'CORR',
            'COVAR_POP',
            'COVAR_SAMP',
            'DELETING',
            'DETERMINISTIC',
            'FALSE',
            'INSERTING',
            'LOCALTIME',
            'LOCALTIMESTAMP',
            'OFFSET',
            'OVER',
            'RDB$RECORD_VERSION',
            'REGR_AVGX',
            'REGR_AVGY',
            'REGR_COUNT',
            'REGR_INTERCEPT',
            'REGR_R2',
            'REGR_SLOPE',
            'REGR_SXX',
            'REGR_SXY',
            'REGR_SYY',
            'RETURN',
            'ROW',
            'SCROLL',
            'SQLSTATE',
            'STDDEV_POP',
            'STDDEV_SAMP',
            'TRUE',
            'UNKNOWN',
            'UPDATING',
            'VAR_POP',
            'VAR_SAMP'
        ]);
    }
}
