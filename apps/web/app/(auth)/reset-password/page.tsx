import { redirect } from 'next/navigation'

export default function ResetPasswordPage(): React.JSX.Element {
  redirect('/forgot-password')
}
