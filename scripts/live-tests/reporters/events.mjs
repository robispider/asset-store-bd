import fs from 'node:fs';
import path from 'node:path';
import { redactObject } from './redaction.mjs';

/**
 * GovStore Live Testing - Streaming JSONL Event Logger
 */
export class EventLogger {
  constructor(runDir) {
    this.runDir = runDir;
    this.eventsFile = path.join(runDir, 'events.jsonl');
    if (!fs.existsSync(runDir)) {
      fs.mkdirSync(runDir, { recursive: true });
    }
  }

  log(eventType, payload = {}) {
    const event = {
      timestamp: new Date().toISOString(),
      type: eventType,
      ...redactObject(payload)
    };

    const line = JSON.stringify(event) + '\n';
    fs.appendFileSync(this.eventsFile, line, 'utf8');
    return event;
  }
}
