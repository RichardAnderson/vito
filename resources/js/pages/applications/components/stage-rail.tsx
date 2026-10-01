import { CheckIcon, CircleDashedIcon, XIcon } from 'lucide-react';

const STAGES = ['clone', 'build', 'pre-flight', 'release'] as const;

export default function StageRail({ currentStage, failed }: { currentStage: string | null; failed: boolean }) {
  const currentIndex = currentStage ? STAGES.indexOf(currentStage as (typeof STAGES)[number]) : -1;

  return (
    <ol className="flex items-center gap-2" aria-label="Deployment stages">
      {STAGES.map((stage, index) => {
        const done = index < currentIndex;
        const active = index === currentIndex;
        const isFailure = active && failed;

        return (
          <li key={stage} className="flex flex-1 items-center gap-2">
            <span
              className={
                isFailure
                  ? 'bg-destructive/10 text-destructive flex size-6 items-center justify-center rounded-full'
                  : done
                    ? 'bg-primary/10 text-primary flex size-6 items-center justify-center rounded-full'
                    : active
                      ? 'bg-accent text-accent-foreground flex size-6 items-center justify-center rounded-full'
                      : 'bg-muted text-muted-foreground flex size-6 items-center justify-center rounded-full'
              }
            >
              {isFailure ? <XIcon className="size-3" /> : done ? <CheckIcon className="size-3" /> : <CircleDashedIcon className="size-3" />}
            </span>
            <span className={active || done ? 'text-sm font-medium' : 'text-muted-foreground text-sm'}>{stage}</span>
            {index < STAGES.length - 1 && <span className="bg-border h-px flex-1" />}
          </li>
        );
      })}
    </ol>
  );
}
