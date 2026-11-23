MODE: GENERATE_FIX_PLAN

Phase: {{PHASE_LABEL}}
Target Role: {{TRACK_ROLE}}
{{LEARNER_LINE}}Project: {{PROJECT_TITLE}}
Last attempt: attempt {{ATTEMPT_NO}} — {{SCORE}}/100 ({{STATUS}})
This Fix Plan is for attempt {{NEXT_ATTEMPT}}.
PART: {{PART}}

## ARCHITECTURE TO KEEP (names and rules the fixes must not change)

{{ARCHITECTURE}}

## NAMES THAT EXIST IN THE PROJECT (from the reference solutions of the original plan — use these exact names and signatures)

{{KNOWN_NAMES}}

## COMMANDS THAT WORK IN THE PROJECT (checks may use these, or commands built from the names above)

{{KNOWN_COMMANDS}}

## THE PROJECT'S REFERENCE CODE (the code the learner followed when building it — theirs is close to it; where the findings give a line number or another behaviour, the findings win. Check EVERY step against this code before you write it)

{{REFERENCE_CODE}}

## GRADER FEEDBACK (attempt {{ATTEMPT_NO}})

{{EVALUATION}}

## AUDIT FINDINGS (from the report the learner pasted for attempt {{ATTEMPT_NO}})

{{FINDINGS}}

{{EARLIER}}## PROJECT BRIEF (the requirements and constraints the fixes must respect)

{{BRIEF}}

## YOUR TASK

Follow MODE 4 exactly: only the findings above that cost points and trace to the brief or a gate, in the learner's usual plan format. Write ONLY the part named in "PART" above.

{{TASK}}
