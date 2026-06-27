'use client'

import { useMemo } from 'react'
import { Area, AreaChart, CartesianGrid, XAxis } from 'recharts'

import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from '@workspace/ui/components/chart'

const DAY_MS = 86_400_000
const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as const

type DayBin = {
  day: string
  label: string
  inboxes: number
}

function buildWeekBins(
  inboxes: { lastEventAt: string | null; eventsCount: number }[],
): DayBin[] {
  const now = Date.now()
  const bins: DayBin[] = []

  for (let i = 6; i >= 0; i--) {
    const date = new Date(now - i * DAY_MS)
    const dayKey = date.toISOString().slice(0, 10)
    bins.push({
      day: dayKey,
      label: DAY_LABELS[date.getDay()]!,
      inboxes: 0,
    })
  }

  for (const inbox of inboxes) {
    if (!inbox.lastEventAt) continue
    const eventDate = new Date(inbox.lastEventAt).toISOString().slice(0, 10)
    const bin = bins.find((b) => b.day === eventDate)
    if (bin) bin.inboxes++
  }

  return bins
}

const chartConfig = {
  inboxes: {
    label: 'Inboxes with activity',
    color: 'var(--chart-1)',
  },
} satisfies ChartConfig

export function EventsChart({
  inboxes,
}: {
  inboxes: { lastEventAt: string | null; eventsCount: number }[]
}) {
  const data = useMemo(() => buildWeekBins(inboxes), [inboxes])
  const hasActivity = data.some((d) => d.inboxes > 0)

  if (!hasActivity) return null

  return (
    <section aria-labelledby="activity-heading">
      <h2
        id="activity-heading"
        className="mb-4 text-base font-semibold text-foreground"
      >
        Activity this week
      </h2>
      <div className="rounded-xl border border-border bg-card p-4 sm:p-6">
        <ChartContainer
          config={chartConfig}
          className="aspect-auto h-[250px] w-full"
        >
          <AreaChart
            data={data}
            margin={{ top: 4, right: 4, bottom: 4, left: -12 }}
          >
            <CartesianGrid strokeDasharray="3 3" vertical={false} />
            <XAxis
              dataKey="label"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
            />
            <ChartTooltip
              content={<ChartTooltipContent indicator="dot" />}
              cursor={false}
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
                  stopOpacity={0.02}
                />
              </linearGradient>
            </defs>
            <Area
              type="natural"
              dataKey="inboxes"
              stroke="var(--color-inboxes)"
              fill="url(#fillActivity)"
              strokeWidth={2}
            />
          </AreaChart>
        </ChartContainer>
      </div>
    </section>
  )
}
