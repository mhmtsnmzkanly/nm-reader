import { ScenarioType } from '../types/domain';

class ScenarioManager {
  private currentScenario: ScenarioType = 'normal_authenticated';
  private listeners: Array<(s: ScenarioType) => void> = [];

  constructor() {
    if (typeof window !== 'undefined' && typeof localStorage !== 'undefined') {
      const saved = localStorage.getItem('nm_reader_scenario');
      if (saved) {
        this.currentScenario = saved as ScenarioType;
      }
    }
  }

  public getScenario(): ScenarioType {
    return this.currentScenario;
  }

  public setScenario(scenario: ScenarioType): void {
    this.currentScenario = scenario;
    if (typeof window !== 'undefined' && typeof localStorage !== 'undefined') {
      localStorage.setItem('nm_reader_scenario', scenario);
    }
    this.listeners.forEach((fn) => fn(scenario));
  }

  public subscribe(fn: (s: ScenarioType) => void): () => void {
    this.listeners.push(fn);
    return () => {
      this.listeners = this.listeners.filter((l) => l !== fn);
    };
  }
}

export const scenarioManager = new ScenarioManager();
