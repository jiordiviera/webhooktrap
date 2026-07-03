import { AbsoluteFill, Easing, Img, interpolate, staticFile, useCurrentFrame } from "remotion";

export function SignOff() {
  const frame = useCurrentFrame();

  const opacity = interpolate(frame, [0, 15], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: Easing.out(Easing.quad),
  });

  const scale = interpolate(frame, [0, 20], [0.94, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: Easing.out(Easing.quad),
  });

  return (
    <AbsoluteFill
      style={{
        backgroundColor: "oklch(0.21 0.04 42)",
        justifyContent: "center",
        alignItems: "center",
      }}
    >
      <div
        style={{
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          gap: 20,
          opacity,
          scale,
        }}
      >
        <Img src={staticFile("logo.png")} style={{ height: 56, width: "auto" }} />
        <p
          style={{
            fontFamily: "ui-monospace, SFMono-Regular, monospace",
            fontSize: 20,
            letterSpacing: "0.08em",
            color: "oklch(0.72 0.11 56)",
            margin: 0,
          }}
        >
          Inspect. Replay. Respond.
        </p>
      </div>
    </AbsoluteFill>
  );
}
