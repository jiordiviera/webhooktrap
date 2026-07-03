import { AbsoluteFill } from "remotion";
import { KineticCenterBuild } from "../remocn/kinetic-center-build";

export function HookBeat() {
  return (
    <AbsoluteFill
      style={{
        backgroundColor: "oklch(0.21 0.04 42)",
        justifyContent: "center",
        alignItems: "center",
      }}
    >
      <KineticCenterBuild
        text="Three moves. The whole product."
        fontSize={56}
        color="oklch(0.97 0.01 42)"
        fontWeight={600}
      />
    </AbsoluteFill>
  );
}
