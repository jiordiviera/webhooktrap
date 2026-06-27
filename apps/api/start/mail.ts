import { Queue } from 'bullmq'
import mail from '@adonisjs/mail/services/main'

const emailsQueue = new Queue('emails')

mail.setMessenger((mailer) => {
  return {
    async queue(mailMessage, config) {
      /**
       * Store the compiled message, config, and mailer name.
       * A worker process will pick this up and send it.
       */
      await emailsQueue.add('send_email', {
        mailMessage,
        config,
        mailerName: mailer.name,
      })
    },
  }
})
