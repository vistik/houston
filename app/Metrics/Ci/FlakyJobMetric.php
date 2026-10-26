<?php

namespace App\Metrics\Ci;

/**
 * A metric of the jobs that failed and then passed on a re-run, which can also be narrowed to one job.
 */
abstract class FlakyJobMetric extends CiMetric {}
