/**
 * Pomoćna logika za prikaz naloga, deljena preko klijentskih ekrana
 * (11 Moje intervencije, 12 Nalog i nalaz, 08 Početna).
 */

import type { ChipState } from '../theme/tokens';
import type { JobStatus, JobType } from '../api/types';

/**
 * design/README.md "Job lifecycle" tabela nabraja "Garancija" kao petu
 * chip varijantu pored četiri statusa (Novo/Zakazano/U toku/Završeno):
 * garancija je tip naloga (JobType), ne status (JobStatus), pa se
 * garancijski chip prikazuje UMJESTO status chipa kad je `type ===
 * 'garancija'`, ne kao dodatna oznaka pored njega.
 */
export function chipStateForJob(job: { status: JobStatus; type?: JobType }): ChipState {
  if (job.type === 'garancija') {
    return 'garancija';
  }
  return job.status;
}
