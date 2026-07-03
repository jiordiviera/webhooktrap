import { TransitionSeries, linearTiming } from "@remotion/transitions";
import { fade } from "@remotion/transitions/fade";
import { whipPan } from "../remocn/whip-pan";
import { HookBeat } from "./HookBeat";
import { TerminalBeat } from "./TerminalBeat";
import { ResultBeat } from "./ResultBeat";
import { SignOff } from "./SignOff";

export const HOOK_FRAMES = 90;
export const WHIP_PAN_FRAMES = 20;
export const TERMINAL_FRAMES = 300;
export const FADE_1_FRAMES = 16;
export const RESULT_FRAMES = 90;
export const FADE_2_FRAMES = 16;
export const SIGNOFF_FRAMES = 48;

export const PROCESS_LOOP_DURATION =
  HOOK_FRAMES +
  TERMINAL_FRAMES +
  RESULT_FRAMES +
  SIGNOFF_FRAMES -
  WHIP_PAN_FRAMES -
  FADE_1_FRAMES -
  FADE_2_FRAMES;

export function ProcessLoop() {
  return (
    <TransitionSeries>
      <TransitionSeries.Sequence durationInFrames={HOOK_FRAMES}>
        <HookBeat />
      </TransitionSeries.Sequence>

      <TransitionSeries.Transition
        timing={linearTiming({ durationInFrames: WHIP_PAN_FRAMES })}
        presentation={whipPan({ direction: "down" })}
      />

      <TransitionSeries.Sequence durationInFrames={TERMINAL_FRAMES}>
        <TerminalBeat />
      </TransitionSeries.Sequence>

      <TransitionSeries.Transition
        timing={linearTiming({ durationInFrames: FADE_1_FRAMES })}
        presentation={fade()}
      />

      <TransitionSeries.Sequence durationInFrames={RESULT_FRAMES}>
        <ResultBeat durationInFrames={RESULT_FRAMES} />
      </TransitionSeries.Sequence>

      <TransitionSeries.Transition
        timing={linearTiming({ durationInFrames: FADE_2_FRAMES })}
        presentation={fade()}
      />

      <TransitionSeries.Sequence durationInFrames={SIGNOFF_FRAMES}>
        <SignOff />
      </TransitionSeries.Sequence>
    </TransitionSeries>
  );
}
