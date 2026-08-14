import React, { useState } from 'react';
import { Sliders, Check } from 'lucide-react';
import { ScenarioType } from '../../types/domain';
import { useAuth } from '../../contexts/AuthContext';

export const DevScenarioSwitcher: React.FC = () => {
  const { scenario, setScenario } = useAuth();
  const [isOpen, setIsOpen] = useState(false);

  const scenarios: { key: ScenarioType; label: string }[] = [
    { key: 'normal_authenticated', label: 'Logged-in User (Normal)' },
    { key: 'normal_guest', label: 'Guest User (Unauthenticated)' },
    { key: 'session_expired', label: 'Session Expired (401)' },
    { key: 'insufficient_coins', label: 'Insufficient Coins (402)' },
    { key: 'forbidden_commenting', label: 'Forbidden Commenting (403)' },
    { key: 'network_error', label: 'Network Error (500)' },
    { key: 'empty_data', label: 'Empty Data Response' },
  ];

  return (
    <div className="fixed bottom-20 right-4 z-40 sm:bottom-6">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-3 py-2 bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 rounded-full shadow-lg text-xs font-mono hover:scale-105 transition-all cursor-pointer"
        title="Mock Scenario Switcher"
      >
        <Sliders className="w-3.5 h-3.5" />
        <span className="hidden sm:inline">Scenario:</span>
        <span className="font-bold text-indigo-400 dark:text-indigo-600">{scenario}</span>
      </button>

      {isOpen && (
        <div className="absolute bottom-12 right-0 w-64 p-3 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl animate-in zoom-in-95">
          <div className="text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 px-2 uppercase tracking-wider">
            Mock Scenarios
          </div>
          <div className="flex flex-col gap-1">
            {scenarios.map((item) => (
              <button
                key={item.key}
                onClick={() => {
                  setScenario(item.key);
                  setIsOpen(false);
                }}
                className={`flex items-center justify-between w-full text-left px-2.5 py-1.5 text-xs rounded-lg transition-colors cursor-pointer ${
                  scenario === item.key
                    ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold'
                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                }`}
              >
                <span>{item.label}</span>
                {scenario === item.key && <Check className="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};
