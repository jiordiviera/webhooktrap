"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { Area, AreaChart, CartesianGrid, XAxis } from "recharts";

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@workspace/ui/components/card";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@workspace/ui/components/chart";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@workspace/ui/components/select";

const DAY_MS = 86_400_000;
const DAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"] as const;

type DayBin = {
  label: string;
  inboxes: number;
};

function buildBins(
  inboxes: { lastEventAt: string | null }[],
  days: number,
): DayBin[] {
  const now = Date.now();
  const bins: DayBin[] = [];

  const step = days >= 60 ? 7 : 1;
  const count = Math.ceil(days / step);

  for (let i = count - 1; i >= 0; i--) {
    const date = new Date(now - i * step * DAY_MS);
    bins.push({
      label:
        step === 1
          ? DAY_LABELS[date.getDay()]!
          : date.toLocaleDateString("en-US", {
              month: "short",
              day: "numeric",
            }),
      inboxes: 0,
    });
  }

  for (const inbox of inboxes) {
    if (!inbox.lastEventAt) continue;
    const eventDate = new Date(inbox.lastEventAt).getTime();
    const diffDays = Math.floor((now - eventDate) / DAY_MS);

    if (diffDays > days) continue;

    const binIndex = Math.floor(diffDays / step);
    const idx = count - 1 - binIndex;
    if (idx >= 0 && idx < count) {
      bins[idx]!.inboxes++;
    }
  }

  return bins;
}

const chartConfig = {
  inboxes: {
    label: "Inboxes with activity",
    color: "var(--chart-1)",
  },
} satisfies ChartConfig;

export function EventsChart({
  inboxes,
}: {
  inboxes: { lastEventAt: string | null; eventsCount: number }[];
}) {
  const [timeRange, setTimeRange] = useState("7d");
  const chartRef = useRef<HTMLDivElement>(null);
  const [chartWidth, setChartWidth] = useState(800);

  useEffect(() => {
    const el = chartRef.current;
    if (!el) return;
    const observer = new ResizeObserver((entries) => {
      const { width } = entries[0]!.contentRect;
      if (width > 0) setChartWidth(width);
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const data = useMemo(() => {
    const days = timeRange === "90d" ? 90 : timeRange === "30d" ? 30 : 7;
    return buildBins(inboxes, days);
  }, [inboxes, timeRange]);

  const hasActivity = data.some((d) => d.inboxes > 0);
  const rangeLabel =
    timeRange === "90d"
      ? "90 days"
      : timeRange === "30d"
        ? "30 days"
        : "7 days";

  return (
    <Card className="pt-0">
      <CardHeader className="flex flex-row items-center gap-2 border-b py-5">
        <div className="grid flex-1 gap-1">
          <CardTitle className="text-base font-semibold">
            Inbox activity
          </CardTitle>
          <CardDescription>
            Inboxes that received a webhook in the last {rangeLabel}
          </CardDescription>
        </div>
        <Select value={timeRange} onValueChange={setTimeRange}>
          <SelectTrigger
            className="hidden w-[140px] sm:flex"
            aria-label="Select time range"
          >
            <SelectValue placeholder="Last 7 days" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="7d">Last 7 days</SelectItem>
            <SelectItem value="30d">Last 30 days</SelectItem>
            <SelectItem value="90d">Last 90 days</SelectItem>
          </SelectContent>
        </Select>
      </CardHeader>
      <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
        {hasActivity ? (
          <ChartContainer
            ref={chartRef}
            config={chartConfig}
            className="aspect-auto h-[250px] w-full"
          >
            <AreaChart data={data} width={chartWidth} height={250}>
              <CartesianGrid vertical={false} />
              <XAxis
                dataKey="label"
                tickLine={false}
                tickMargin={10}
                axisLine={false}
                minTickGap={32}
                tickFormatter={(value) => {
                  const date = new Date(value);
                  return date.toLocaleDateString("en-US", {
                    month: "short",
                    day: "numeric",
                  });
                }}
              />
              <ChartTooltip
                cursor={false}
                content={
                  <ChartTooltipContent
                    labelFormatter={(value) => {
                      return new Date(value).toLocaleDateString("en-US", {
                        month: "short",
                        day: "numeric",
                      });
                    }}
                    indicator="dot"
                  />
                }
              />
              <defs>
                <linearGradient id="fillActivity" x1="0" y1="0" x2="0" y2="1">
                  <stop
                    offset="5%"
                    stopColor="var(--color-inboxes)"
                    stopOpacity={0.3}
                  />
                  <stop
                    offset="95%"
                    stopColor="var(--color-inboxes)"
                    stopOpacity={0.05}
                  />
                </linearGradient>
              </defs>
              <Area
                dataKey="inboxes"
                type="monotone"
                fill="url(#fillActivity)"
                fillOpacity={1}
                stroke="var(--color-inboxes)"
                strokeWidth={2}
                activeDot={{ r: 6, strokeWidth: 2 }}
                stackId="inboxes"
              />
            </AreaChart>
          </ChartContainer>
        ) : (
          <div className="flex h-[200px] items-center justify-center">
            <p className="text-sm text-muted-foreground">
              No activity in this period
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
