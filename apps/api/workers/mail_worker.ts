import { Worker } from 'bullmq'
import mail from '@adonisjs/mail/services/main'

new Worker('emails', async (job) => {
  if (job.name === 'send_email') {
    const { mailMessage, config, mailerName } = job.data

    /**
     * Send the pre-compiled message using the specified mailer
     */
    await mail.use(mailerName).sendCompiled(mailMessage, config)
  }
})
