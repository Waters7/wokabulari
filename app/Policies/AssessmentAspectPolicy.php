<?php

namespace App\Policies;

/**
 * Master aspek penilaian: dikelola admin program; pengajar cukup melihat & memilih untuk kelasnya.
 */
class AssessmentAspectPolicy extends PermissionPolicy
{
    protected string $manage = 'assessment-aspect.manage';

    protected ?string $view = 'assessment.manage';
}
