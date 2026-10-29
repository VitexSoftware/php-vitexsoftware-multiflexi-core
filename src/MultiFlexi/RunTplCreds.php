<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi;

/**
 * Description of RunTplCreds.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class RunTplCreds extends Engine
{
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'runtplcreds';
        parent::__construct($identifier, $options);
    }

    public function getRuntemplatesForCredential($credentials_id)
    {
        return $this->listingQuery()->where(['credentials_id' => $credentials_id]);
    }

    public function getCredentialsForRuntemplate($runtemplates_id)
    {
        return $this->listingQuery()
            ->select(['runtplcreds.credentials_id', 'credentials.name', 'credentials.formType'])
            ->where(['runtemplate_id' => $runtemplates_id])
            ->leftJoin('credentials ON credentials.id = runtplcreds.credentials_id');
    }

    public function bind(int $runtemplate_id, int $credentials_id, string $reqType)
    {
        $this->unbindAll($runtemplate_id, $reqType);
        $this->insertToSQL(['runtemplate_id' => $runtemplate_id, 'credentials_id' => $credentials_id]);

        // Drop stored RunTemplate config fields shadowed by the assigned credential.
        (new RunTemplate($runtemplate_id))->dropStoredConfigProvidedByCredential(new Credential($credentials_id));

        return true;
    }

    /**
     * Assign credentials to a RunTemplate based only on the application's requirements.
     *
     * For every requirement (credential prototype code) exactly one credential of
     * the RunTemplate's company must exist. Nothing is bound unless all inspected
     * requirements can be resolved unambiguously.
     *
     * @param null|array<string> $only restrict to these requirements (null = all of the application)
     *
     * @return array<string, array{status: string, credential_id?: int, candidates?: array<int, string>}>
     *                                                                                                        status is one of: assigned, unchanged, missing, ambiguous, unknown
     */
    public function assignByRequirements(int $runtemplate_id, ?array $only = null): array
    {
        $runtemplate = new RunTemplate($runtemplate_id);
        $companyId = $runtemplate->getDataValue('company_id');
        $requirements = $runtemplate->getRequirements();
        $report = [];

        if ($only !== null) {
            foreach (array_diff($only, $requirements) as $unknown) {
                $report[$unknown] = ['status' => 'unknown'];
            }

            $requirements = array_values(array_intersect($requirements, $only));
        }

        $assigned = $runtemplate->getCredentialsAssigned();
        $toBind = [];

        foreach ($requirements as $requirement) {
            $candidates = [];

            foreach ((new Credential())->listingQuery()->leftJoin('credential_type ON credential_type.id = credentials.credential_type_id')->where(['credentials.company_id' => $companyId, 'credential_type.prototype' => $requirement]) as $candidate) {
                $candidates[(int) $candidate['id']] = (string) $candidate['name'];
            }

            if ($candidates === []) {
                $report[$requirement] = ['status' => 'missing'];
            } elseif (\count($candidates) > 1) {
                $report[$requirement] = ['status' => 'ambiguous', 'candidates' => $candidates];
            } else {
                $credentialId = (int) array_key_first($candidates);

                if (isset($assigned[$requirement]) && (int) $assigned[$requirement]['credentials_id'] === $credentialId) {
                    $report[$requirement] = ['status' => 'unchanged', 'credential_id' => $credentialId];
                } else {
                    $report[$requirement] = ['status' => 'assigned', 'credential_id' => $credentialId];
                    $toBind[$requirement] = $credentialId;
                }
            }
        }

        $failed = array_filter($report, static fn (array $r): bool => \in_array($r['status'], ['missing', 'ambiguous', 'unknown'], true));

        if ($failed === []) {
            foreach ($toBind as $requirement => $credentialId) {
                $this->bind($runtemplate_id, $credentialId, $requirement);
            }
        } else {
            foreach ($report as $requirement => $item) {
                if ($item['status'] === 'assigned') {
                    $report[$requirement]['status'] = 'skipped';
                }
            }
        }

        return $report;
    }

    public function unbind(int $runtemplate_id, int $credentials_id)
    {
        return $this->deleteFromSQL(['runtemplate_id' => $runtemplate_id, 'credentials_id' => $credentials_id]);
    }

    public function unbindAll(int $runtemplate_id, string $reqType): void
    {
        $runtemplater = new RunTemplate($runtemplate_id);
        $kredenc = new Credential();
        $candidates = $kredenc->listingQuery()->leftJoin('credential_type ON credential_type.id = credentials.credential_type_id')->where(['credentials.company_id' => $runtemplater->getDataValue('company_id'), 'credential_type.prototype' => $reqType]);

        foreach ($candidates as $candidat) {
            $this->unbind($runtemplate_id, $candidat['id']);
        }
    }
}
