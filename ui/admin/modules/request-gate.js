export function createRequestGate() {
  let token = 0;
  return Object.freeze({
    begin: () => ++token,
    isCurrent: (candidate) => candidate === token,
    reset: () => ++token,
  });
}
