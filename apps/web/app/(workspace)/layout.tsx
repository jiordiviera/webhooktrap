import { WorkspaceLayout } from '@/app/components/dashboard/workspace-layout'

export default function Layout({ children }: { children: React.ReactNode }) {
  return <WorkspaceLayout>{children}</WorkspaceLayout>
}